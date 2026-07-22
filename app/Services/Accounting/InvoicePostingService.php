<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Support\ConnectionScopedTransaction;
use Modules\ERP\Support\ConnectionScopedModels;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\TaxCode;
use Modules\ERP\Services\Company\ErpCompanySettings;
use Modules\ERP\Services\Payments\PaymentScheduleGeneratorService;
use Modules\ERP\Services\Purchasing\ThreeWayMatchService;
use Modules\ERP\Services\SalesOrders\SalesOrderEvasionService;
use Modules\ERP\Services\Taxation\TaxLineCalculator;
use Modules\ERP\Support\Decimal;
use Modules\Core\Services\OutboxRecorder;

final readonly class InvoicePostingService
{
    public function __construct(
        private ChartOfAccountsInstaller $chart_of_accounts_installer,
        private CreditNoteService $credit_note_service,
        private DocumentNumberAllocator $document_number_allocator,
        private InvoiceDeliveryNoteValidationService $invoice_delivery_note_validation_service,
        private JournalPostingService $journal_posting_service,
        private PaymentScheduleGeneratorService $payment_schedule_generator_service,
        private SalesOrderEvasionService $sales_order_evasion_service,
        private ErpCompanySettings $erp_company_settings,
        private ThreeWayMatchService $three_way_match_service,
        private VatRegisterService $vat_register_service,
        private TaxLineCalculator $tax_line_calculator,
        private OutboxRecorder $outbox_recorder,
    ) {}

    public function post(Invoice $invoice): void
    {
        ConnectionScopedTransaction::run($invoice, function (ConnectionScopedModels $models) use ($invoice): void {
            $locked = $models->query(Invoice::class)->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->journal_entry_id !== null) {
                return;
            }

            $company = $models->query(Company::class)->withoutGlobalScopes()->findOrFail($locked->company_id);
            $this->chart_of_accounts_installer->installWhenEmpty($company);

            $lines = $models->query(InvoiceLine::class)
                ->where('invoice_id', $locked->id)
                ->orderBy('line_no')
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => ['Invoice must have at least one line before posting.'],
                ]);
            }

            $this->invoice_delivery_note_validation_service->validateForPosting($locked, $lines);
            $this->applyThreeWayMatch($company, $invoice, $lines);

            $document_type = $locked->direction === InvoiceDirection::Sale
                ? DocumentType::SalesInvoice
                : DocumentType::PurchaseInvoice;

            if ($locked->invoice_type === InvoiceType::CreditNote) {
                $document_type = $locked->direction === InvoiceDirection::Sale
                    ? DocumentType::SalesCreditNote
                    : DocumentType::PurchaseCreditNote;
            } elseif ($locked->invoice_type === InvoiceType::DebitNote) {
                $document_type = $locked->direction === InvoiceDirection::Sale
                    ? DocumentType::SalesDebitNote
                    : DocumentType::PurchaseDebitNote;
            }

            $posted_at = $this->postedAtForPosting($invoice, $locked);

            $fiscal_year = $posted_at->year;
            $reference = $this->document_number_allocator->next($company, $document_type, $fiscal_year);
            $invoice->reference = $reference;

            [$net_total, $tax_total, $gross_total] = $this->resolveAndSnapshotTaxes($models, $lines);

            if ($locked->invoice_type === InvoiceType::CreditNote) {
                $this->credit_note_service->validateCreditNoteTotal($locked, $models);
                $net_total = Decimal::negate($net_total);
                $tax_total = Decimal::negate($tax_total);
                $gross_total = Decimal::negate($gross_total);
            }

            $journal_lines = $this->buildJournalLines($models, $company, $locked->direction, $locked->currency, $net_total, $tax_total, $gross_total);
            $fiscal_period = $this->resolveFiscalPeriod($models, $company, $posted_at);
            $entry = $this->journal_posting_service->post(
                $company,
                $journal_lines,
                $fiscal_period,
                'Invoice posted ' . $reference,
                null,
                $locked,
                $posted_at,
            );

            $this->vat_register_service->register($invoice);

            $this->payment_schedule_generator_service->generate($locked, $gross_total);

            $this->applySalesOrderInvoicingProgress($models, $locked, $lines, true);
            $invoice->journal_entry_id = $this->modelId($entry);

            $this->outbox_recorder->record('erp.invoice.posted', $locked, [
                'company_id' => (int) $locked->company_id,
                'reference' => $reference,
                'journal_entry_id' => $this->modelId($entry),
            ]);
        });
    }

    public function unpost(Invoice $invoice): void
    {
        ConnectionScopedTransaction::run($invoice, function (ConnectionScopedModels $models) use ($invoice): void {
            $locked = $models->query(Invoice::class)->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $lines = $models->query(InvoiceLine::class)
                ->where('invoice_id', $locked->id)
                ->orderBy('line_no')
                ->get();

            if ($locked->journal_entry_id !== null) {
                $entry = $models->query(JournalEntry::class)->withoutGlobalScopes()->find($locked->journal_entry_id);

                if ($entry !== null) {
                    $company = $models->query(Company::class)->withoutGlobalScopes()->findOrFail($locked->company_id);
                    $reason = $locked->reference !== null && $locked->reference !== ''
                        ? 'Invoice unposted ' . $locked->reference
                        : 'Invoice unposted #' . (string) $locked->id;
                    $this->journal_posting_service->reverse($entry, $company, $reason);
                }
            }

            $this->vat_register_service->unregister($locked);

            $this->payment_schedule_generator_service->removeAll($locked);

            $this->applySalesOrderInvoicingProgress($models, $locked, $lines, false);

            $invoice->reference = null;
            $invoice->journal_entry_id = null;
        });
    }

    /**
     * @param  Collection<int, InvoiceLine>  $lines
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveAndSnapshotTaxes(ConnectionScopedModels $models, Collection $lines): array
    {
        $net_total = '0.0000';
        $tax_total = '0.0000';
        $tax_code_ids = $lines->pluck('tax_code_id')->filter()->unique()->values();
        $tax_codes = $tax_code_ids->isEmpty()
            ? collect()
            : $models->query(TaxCode::class)
                ->withoutGlobalScopes()
                ->whereIn('id', $tax_code_ids)
                ->get()
                ->keyBy('id');

        foreach ($lines as $line) {
            $line_net = Decimal::mul((string) $line->quantity, (string) $line->unit_price);
            $line_tax = '0.0000';

            if ($line->tax_code_id !== null) {
                $tax_code = $tax_codes->get($line->tax_code_id);

                if (! $tax_code instanceof TaxCode) {
                    throw ValidationException::withMessages([
                        'tax_code_id' => ['The selected tax code is invalid.'],
                    ]);
                }

                $line_tax = $this->tax_line_calculator->lineTax($line_net, (string) $tax_code->rate);

                $line->tax_code = $tax_code->code;
                $line->tax_rate = $tax_code->rate;
                $line->tax_label = $tax_code->label;

                $models->query(InvoiceLine::class)
                    ->whereKey($line->id)
                    ->update([
                        'tax_code' => $tax_code->code,
                        'tax_rate' => $tax_code->rate,
                        'tax_label' => $tax_code->label,
                    ]);
            }

            $net_total = Decimal::add($net_total, $line_net);
            $tax_total = Decimal::add($tax_total, $line_tax);
        }

        $gross_total = Decimal::add($net_total, $tax_total);

        return [$net_total, $tax_total, $gross_total];
    }

    /**
     * @return list<array{
     *     account_id: int,
     *     amount_doc: string,
     *     currency_doc: string,
     *     amount_local: string,
     *     currency_local: string,
     *     fx_rate: string,
     *     description: string,
     * }>
     */
    private function buildJournalLines(
        ConnectionScopedModels $models,
        Company $company,
        InvoiceDirection $direction,
        string $currency,
        string $net_total,
        string $tax_total,
        string $gross_total,
    ): array {
        $fx_rate = '1';

        if ($direction === InvoiceDirection::Sale) {
            $receivable = $this->findAccountByRole($models, $company, 'trade_receivable');
            $revenue = $this->findAccountByRole($models, $company, 'sales_revenue');
            $vat_output = $this->findAccountByRole($models, $company, 'vat_output');

            $lines = [
                $this->line($this->modelId($receivable), $gross_total, $currency, $fx_rate, 'Trade receivable'),
                $this->line($this->modelId($revenue), Decimal::negate($net_total), $currency, $fx_rate, 'Sales revenue'),
            ];

            if (! Decimal::isZero($tax_total)) {
                $lines[] = $this->line($this->modelId($vat_output), Decimal::negate($tax_total), $currency, $fx_rate, 'VAT output');
            }

            return $lines;
        }

        $expense = $this->findAccountByRole($models, $company, 'purchase_expense');
        $vat_input = $this->findAccountByRole($models, $company, 'vat_input');
        $payable = $this->findAccountByRole($models, $company, 'trade_payable');

        $lines = [
            $this->line($this->modelId($expense), $net_total, $currency, $fx_rate, 'Purchase expense'),
        ];

        if (! Decimal::isZero($tax_total)) {
            $lines[] = $this->line($this->modelId($vat_input), $tax_total, $currency, $fx_rate, 'VAT input');
        }

        $lines[] = $this->line($this->modelId($payable), Decimal::negate($gross_total), $currency, $fx_rate, 'Trade payable');

        return $lines;
    }

    /**
     * @param  Collection<int, InvoiceLine>  $lines
     */
    private function applyThreeWayMatch(Company $company, Invoice $invoice, Collection $lines): void
    {
        if ($invoice->direction !== InvoiceDirection::Purchase) {
            return;
        }

        $price_tolerance = $this->erp_company_settings->priceTolerancePercent($company);
        $qty_tolerance = $this->erp_company_settings->qtyTolerancePercent($company);
        $force = $invoice->forceThreeWayMatchOnPosting;

        foreach ($lines as $line) {
            $result = $this->three_way_match_service->validate(
                $line,
                $price_tolerance,
                $qty_tolerance,
                $force,
            );

            $line->match_status = $result['status'];
            $line->match_discrepancy = $result['discrepancies'] !== [] ? $result['discrepancies'] : null;
            $line->save();
        }
    }

    /**
     * @param  Collection<int, InvoiceLine>  $lines
     */
    private function applySalesOrderInvoicingProgress(ConnectionScopedModels $models, Invoice $invoice, Collection $lines, bool $forward): void
    {
        if ($invoice->direction !== InvoiceDirection::Sale) {
            return;
        }

        $quantities_by_order = [];

        foreach ($lines as $line) {
            if ($line->sales_order_line_id === null) {
                continue;
            }

            $sales_order_line = $line->sales_order_line()->withoutGlobalScopes()->first();

            if ($sales_order_line === null) {
                continue;
            }

            $order_id = (int) $sales_order_line->sales_order_id;
            $line_id = (int) $sales_order_line->id;
            $quantity = (float) $line->quantity;

            if ($quantity <= 0.0) {
                continue;
            }

            if (! isset($quantities_by_order[$order_id])) {
                $quantities_by_order[$order_id] = [];
            }

            $quantities_by_order[$order_id][$line_id] = number_format(
                (float) ($quantities_by_order[$order_id][$line_id] ?? 0) + $quantity,
                4,
                '.',
                '',
            );
        }

        foreach ($quantities_by_order as $order_id => $line_quantities) {
            $sales_order = $models->query(\Modules\ERP\Models\SalesOrder::class)->whereKey($order_id)->lockForUpdate()->first();

            if ($sales_order === null) {
                continue;
            }

            if ($forward) {
                $this->sales_order_evasion_service->registerInvoice($sales_order, $line_quantities);
            } else {
                $this->sales_order_evasion_service->unregisterInvoice($sales_order, $line_quantities);
            }
        }
    }

    private function findAccountByRole(ConnectionScopedModels $models, Company $company, string $role): Account
    {
        $account = $models->query(Account::class)
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('meta->erp_role', $role)
            ->first();

        if ($account !== null) {
            return $account;
        }

        throw ValidationException::withMessages([
            'accounts' => ['Chart of accounts missing role: ' . $role],
        ]);
    }

    private function postedAtForPosting(Invoice $invoice, Invoice $locked): CarbonImmutable
    {
        $posted_at = $invoice->posted_at ?? $locked->posted_at;

        if ($posted_at instanceof CarbonImmutable) {
            return $posted_at;
        }

        if ($posted_at !== null) {
            return CarbonImmutable::parse($posted_at);
        }

        return CarbonImmutable::now();
    }

    private function resolveFiscalPeriod(ConnectionScopedModels $models, Company $company, CarbonImmutable $posted_at): ?FiscalPeriod
    {
        return $models->query(FiscalPeriod::class)
            ->withoutGlobalScopes()
            ->whereHas('fiscal_year', static function (\Illuminate\Database\Eloquent\Builder $query) use ($company): void {
                $query->withoutGlobalScopes()
                    ->where('company_id', $company->id);
            })
            ->whereDate('start_date', '<=', $posted_at)
            ->whereDate('end_date', '>=', $posted_at)
            ->first();
    }

    /**
     * @return array{
     *     account_id: int,
     *     amount_doc: string,
     *     currency_doc: string,
     *     amount_local: string,
     *     currency_local: string,
     *     fx_rate: string,
     *     description: string,
     * }
     */
    private function line(int $account_id, string $amount, string $currency, string $fx_rate, string $description): array
    {
        return [
            'account_id' => $account_id,
            'amount_doc' => $amount,
            'currency_doc' => $currency,
            'amount_local' => $amount,
            'currency_local' => $currency,
            'fx_rate' => $fx_rate,
            'description' => $description,
        ];
    }

    private function modelId(Account|JournalEntry $model): int
    {
        return is_int($model->id) ? $model->id : (int) $model->id;
    }
}
