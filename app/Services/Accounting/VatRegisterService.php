<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\VatRegisterType;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\VatRegisterEntry;
use Modules\ERP\Services\Taxation\TaxLineCalculator;
use Modules\ERP\Support\Decimal;

final class VatRegisterService
{
    public function __construct(private TaxLineCalculator $tax_line_calculator) {}

    public function register(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $company_id = (int) $invoice->company_id;
            $posted_at = $invoice->posted_at instanceof CarbonImmutable
                ? $invoice->posted_at
                : CarbonImmutable::parse($invoice->posted_at);

            $register_type = $invoice->direction === InvoiceDirection::Sale
                ? VatRegisterType::Sales
                : VatRegisterType::Purchases;

            $is_credit_note = $invoice->invoice_type === InvoiceType::CreditNote;

            $fiscal_year = FiscalYear::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company_id)
                ->where('year', $posted_at->year)
                ->first();

            if ($fiscal_year === null) {
                throw ValidationException::withMessages([
                    'fiscal_year' => ['No fiscal year found for year ' . $posted_at->year . '.'],
                ]);
            }

            $fiscal_year_id = (int) $fiscal_year->id;

            $lines = InvoiceLine::query()
                ->where('invoice_id', (int) $invoice->id)
                ->whereNotNull('tax_code_id')
                ->get();

            $grouped = $lines->groupBy('tax_code_id');

            foreach ($grouped as $tax_code_id => $group) {
                $taxable_amount = '0.0000';
                $tax_amount = '0.0000';

                foreach ($group as $line) {
                    $line_net = Decimal::mul((string) $line->quantity, (string) $line->unit_price);
                    $line_tax = $this->tax_line_calculator->lineTax($line_net, (string) $line->tax_rate);

                    $taxable_amount = Decimal::add($taxable_amount, $line_net);
                    $tax_amount = Decimal::add($tax_amount, $line_tax);
                }

                if ($is_credit_note) {
                    $taxable_amount = Decimal::negate($taxable_amount);
                    $tax_amount = Decimal::negate($tax_amount);
                }

                $protocol_number = $this->nextProtocolNumber($company_id, $register_type->value, $fiscal_year_id);

                VatRegisterEntry::query()->create([
                    'company_id' => $company_id,
                    'invoice_id' => (int) $invoice->id,
                    'register_type' => $register_type->value,
                    'protocol_number' => $protocol_number,
                    'registration_date' => $posted_at->toDateString(),
                    'fiscal_year_id' => $fiscal_year_id,
                    'tax_code_id' => (int) $tax_code_id,
                    'taxable_amount' => $taxable_amount,
                    'tax_amount' => $tax_amount,
                ]);
            }
        });
    }

    public function unregister(Invoice $invoice): void
    {
        VatRegisterEntry::query()
            ->where('invoice_id', (int) $invoice->id)
            ->forceDelete();
    }

    private function nextProtocolNumber(int $company_id, string $register_type, int $fiscal_year_id): int
    {
        $max = VatRegisterEntry::query()
            ->where('company_id', $company_id)
            ->where('register_type', $register_type)
            ->where('fiscal_year_id', $fiscal_year_id)
            ->lockForUpdate()
            ->max('protocol_number');

        return (is_numeric($max) ? (int) $max : 0) + 1;
    }
}
