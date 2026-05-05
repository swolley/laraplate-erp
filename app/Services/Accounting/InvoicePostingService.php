<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\TaxCode;
use Modules\ERP\Services\SalesOrders\SalesOrderEvasionService;

final class InvoicePostingService
{
    public function __construct(
        private readonly ChartOfAccountsInstaller $chart_of_accounts_installer,
        private readonly JournalPostingService $journal_posting_service,
        private readonly SalesOrderEvasionService $sales_order_evasion_service,
    ) {}

    public function post(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey((int) $invoice->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->journal_entry_id !== null) {
                return;
            }

            $company = Company::query()->withoutGlobalScopes()->findOrFail((int) $locked->company_id);
            $this->chart_of_accounts_installer->installWhenEmpty($company);

            $lines = InvoiceLine::query()
                ->where('invoice_id', (int) $locked->id)
                ->orderBy('line_no')
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => ['Invoice must have at least one line before posting.'],
                ]);
            }

            [$net_total, $tax_total, $gross_total] = $this->resolveAndSnapshotTaxes($lines);
            $journal_lines = $this->buildJournalLines($company, $locked->direction, $locked->currency, $net_total, $tax_total, $gross_total);
            $entry = $this->journal_posting_service->post(
                $company,
                $journal_lines,
                null,
                'Invoice posted #' . (int) $locked->id,
                null,
                $locked,
            );

            $this->applySalesOrderInvoicingProgress($locked, $lines, true);
            $invoice->journal_entry_id = (int) $entry->getKey();
        });
    }

    public function unpost(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey((int) $invoice->getKey())->lockForUpdate()->firstOrFail();

            $lines = InvoiceLine::query()
                ->where('invoice_id', (int) $locked->id)
                ->orderBy('line_no')
                ->get();

            if ($locked->journal_entry_id !== null) {
                $entry = JournalEntry::query()->withoutGlobalScopes()->find((int) $locked->journal_entry_id);

                if ($entry !== null) {
                    $company = Company::query()->withoutGlobalScopes()->findOrFail((int) $locked->company_id);
                    $this->journal_posting_service->reverse($entry, $company, 'Invoice unposted #' . (int) $locked->id);
                }
            }

            $this->applySalesOrderInvoicingProgress($locked, $lines, false);

            $invoice->journal_entry_id = null;
        });
    }

    /**
     * @param  Collection<int, InvoiceLine>  $lines
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveAndSnapshotTaxes(Collection $lines): array
    {
        $net_total = '0.0000';
        $tax_total = '0.0000';

        foreach ($lines as $line) {
            $line_net = $this->mul((string) $line->quantity, (string) $line->unit_price);
            $line_tax = '0.0000';

            if ($line->tax_code_id !== null) {
                $tax_code = TaxCode::query()->withoutGlobalScopes()->findOrFail((int) $line->tax_code_id);
                $line_tax = $this->round4(((float) $line_net * (float) $tax_code->rate) / 100);

                $line->tax_code = $tax_code->code;
                $line->tax_rate = (string) $tax_code->rate;
                $line->tax_label = $tax_code->label;
                $line->save();
            }

            $net_total = $this->add($net_total, $line_net);
            $tax_total = $this->add($tax_total, $line_tax);
        }

        $gross_total = $this->add($net_total, $tax_total);

        return [$net_total, $tax_total, $gross_total];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildJournalLines(
        Company $company,
        InvoiceDirection $direction,
        string $currency,
        string $net_total,
        string $tax_total,
        string $gross_total,
    ): array {
        $fx_rate = '1';

        if ($direction === InvoiceDirection::Sale) {
            $receivable = $this->findAccountByRole($company, 'trade_receivable');
            $revenue = $this->findAccountByRole($company, 'sales_revenue');
            $vat_output = $this->findAccountByRole($company, 'vat_output');

            $lines = [
                $this->line((int) $receivable->id, $gross_total, $currency, $fx_rate, 'Trade receivable'),
                $this->line((int) $revenue->id, $this->neg($net_total), $currency, $fx_rate, 'Sales revenue'),
            ];

            if ($this->asFloat($tax_total) > 0) {
                $lines[] = $this->line((int) $vat_output->id, $this->neg($tax_total), $currency, $fx_rate, 'VAT output');
            }

            return $lines;
        }

        $expense = $this->findAccountByRole($company, 'purchase_expense');
        $vat_input = $this->findAccountByRole($company, 'vat_input');
        $payable = $this->findAccountByRole($company, 'trade_payable');

        $lines = [
            $this->line((int) $expense->id, $net_total, $currency, $fx_rate, 'Purchase expense'),
        ];

        if ($this->asFloat($tax_total) > 0) {
            $lines[] = $this->line((int) $vat_input->id, $tax_total, $currency, $fx_rate, 'VAT input');
        }

        $lines[] = $this->line((int) $payable->id, $this->neg($gross_total), $currency, $fx_rate, 'Trade payable');

        return $lines;
    }

    /**
     * @param  Collection<int, InvoiceLine>  $lines
     */
    private function applySalesOrderInvoicingProgress(Invoice $invoice, Collection $lines, bool $forward): void
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
            $quantity = (int) (float) $line->quantity;

            if ($quantity <= 0) {
                continue;
            }

            if (! isset($quantities_by_order[$order_id])) {
                $quantities_by_order[$order_id] = [];
            }

            $quantities_by_order[$order_id][$line_id] = ($quantities_by_order[$order_id][$line_id] ?? 0) + $quantity;
        }

        foreach ($quantities_by_order as $order_id => $line_quantities) {
            $sales_order = \Modules\ERP\Models\SalesOrder::query()->whereKey($order_id)->lockForUpdate()->first();

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

    private function findAccountByRole(Company $company, string $role): Account
    {
        $account = Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', (int) $company->id)
            ->where('meta->erp_role', $role)
            ->first();

        if ($account !== null) {
            return $account;
        }

        throw ValidationException::withMessages([
            'accounts' => ['Chart of accounts missing role: ' . $role],
        ]);
    }

    /**
     * @return array<string, mixed>
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

    private function round4(float $value): string
    {
        return number_format(round($value, 4), 4, '.', '');
    }

    private function add(string $a, string $b): string
    {
        return $this->round4((float) $a + (float) $b);
    }

    private function mul(string $a, string $b): string
    {
        return $this->round4((float) $a * (float) $b);
    }

    private function neg(string $value): string
    {
        return $this->round4(-1 * (float) $value);
    }

    private function asFloat(string $value): float
    {
        return (float) $value;
    }
}
