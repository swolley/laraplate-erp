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

final class VatRegisterService
{
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
                    $line_net = $this->round4((float) $line->quantity * (float) $line->unit_price);
                    $line_tax = $this->round4(((float) $line_net * (float) $line->tax_rate) / 100);

                    $taxable_amount = $this->add($taxable_amount, $line_net);
                    $tax_amount = $this->add($tax_amount, $line_tax);
                }

                if ($is_credit_note) {
                    $taxable_amount = $this->neg($taxable_amount);
                    $tax_amount = $this->neg($tax_amount);
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

    private function round4(float $value): string
    {
        return number_format(round($value, 4), 4, '.', '');
    }

    private function add(string $a, string $b): string
    {
        return $this->round4((float) $a + (float) $b);
    }

    private function neg(string $value): string
    {
        return $this->round4(-1 * (float) $value);
    }
}
