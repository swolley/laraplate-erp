<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;

final class CreditNoteService
{
    /**
     * Create a credit note from an existing posted invoice.
     * Copies lines from the original with quantities/amounts, does NOT post automatically.
     */
    public function createFromInvoice(Invoice $original, ?array $line_overrides = null): Invoice
    {
        return DB::transaction(function () use ($original, $line_overrides): Invoice {
            if ($original->journal_entry_id === null) {
                throw ValidationException::withMessages([
                    'invoice' => ['Cannot create credit note from an unposted invoice.'],
                ]);
            }

            if ($original->invoice_type !== InvoiceType::Invoice) {
                throw ValidationException::withMessages([
                    'invoice' => ['Credit notes can only be created from regular invoices.'],
                ]);
            }

            $existing_cn_total = $this->getExistingCreditNoteTotal($original);
            $original_total = $this->getInvoiceGrossTotal($original);
            $remaining = round((float) $original_total - (float) $existing_cn_total, 4);

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'invoice' => ['This invoice has already been fully credited.'],
                ]);
            }

            $credit_note = Invoice::query()->create([
                'company_id' => $original->company_id,
                'direction' => $original->direction,
                'invoice_type' => InvoiceType::CreditNote->value,
                'credited_invoice_id' => $original->id,
                'currency' => $original->currency,
                'notes' => 'Credit note for invoice ' . ($original->reference ?? '#' . $original->id),
            ]);

            $original_lines = InvoiceLine::query()
                ->where('invoice_id', (int) $original->id)
                ->orderBy('line_no')
                ->get();

            $line_no = 1;

            foreach ($original_lines as $original_line) {
                $quantity = $line_overrides[$original_line->id]['quantity']
                    ?? (string) $original_line->quantity;
                $unit_price = $line_overrides[$original_line->id]['unit_price']
                    ?? (string) $original_line->unit_price;

                if ((float) $quantity <= 0) {
                    continue;
                }

                InvoiceLine::query()->create([
                    'invoice_id' => $credit_note->id,
                    'line_no' => $line_no++,
                    'description' => $original_line->description,
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                    'tax_code_id' => $original_line->tax_code_id,
                    'sales_order_line_id' => $original_line->sales_order_line_id,
                ]);
            }

            if ($line_no === 1) {
                throw ValidationException::withMessages([
                    'lines' => ['Credit note must have at least one line with quantity > 0.'],
                ]);
            }

            return $credit_note;
        });
    }

    /**
     * Validate that the credit note total does not exceed the remaining creditable amount.
     */
    public function validateCreditNoteTotal(Invoice $credit_note): void
    {
        if ($credit_note->credited_invoice_id === null) {
            return;
        }

        $original = Invoice::query()->withoutGlobalScopes()->findOrFail((int) $credit_note->credited_invoice_id);
        $original_total = (float) $this->getInvoiceGrossTotal($original);
        $existing_cn_total = (float) $this->getExistingCreditNoteTotal($original, (int) $credit_note->id);
        $cn_total = (float) $this->getInvoiceGrossTotal($credit_note);

        if (round($existing_cn_total + $cn_total, 4) > round($original_total, 4)) {
            throw ValidationException::withMessages([
                'total' => ['Credit note total exceeds remaining creditable amount.'],
            ]);
        }
    }

    private function getInvoiceGrossTotal(Invoice $invoice): string
    {
        $lines = InvoiceLine::query()
            ->where('invoice_id', (int) $invoice->id)
            ->get();

        $total = 0.0;

        foreach ($lines as $line) {
            $line_net = (float) $line->quantity * (float) $line->unit_price;
            $line_tax = 0.0;

            if ($line->tax_rate !== null && $line->tax_rate !== '') {
                $line_tax = round($line_net * (float) $line->tax_rate / 100, 4);
            }
            $total += $line_net + $line_tax;
        }

        return number_format(round($total, 4), 4, '.', '');
    }

    private function getExistingCreditNoteTotal(Invoice $original, ?int $exclude_id = null): string
    {
        $query = Invoice::query()->withoutGlobalScopes()
            ->where('credited_invoice_id', (int) $original->id)
            ->where('invoice_type', InvoiceType::CreditNote->value)
            ->whereNotNull('journal_entry_id');

        if ($exclude_id !== null) {
            $query->where('id', '!=', $exclude_id);
        }

        $cn_ids = $query->pluck('id')->all();

        if (empty($cn_ids)) {
            return '0.0000';
        }

        $total = 0.0;

        foreach ($cn_ids as $cn_id) {
            $cn = Invoice::query()->withoutGlobalScopes()->find($cn_id);

            if ($cn !== null) {
                $total += (float) $this->getInvoiceGrossTotal($cn);
            }
        }

        return number_format(round($total, 4), 4, '.', '');
    }
}
