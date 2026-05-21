<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;

/**
 * Validates optional invoice line links to posted delivery note lines before fiscal posting.
 */
final readonly class InvoiceDeliveryNoteValidationService
{
    /**
     * @param  Collection<int, InvoiceLine>  $lines
     */
    public function validateForPosting(Invoice $invoice, Collection $lines): void
    {
        if ($invoice->direction !== InvoiceDirection::Sale) {
            return;
        }

        $lines->loadMissing('delivery_note_lines.delivery_note');

        foreach ($lines as $invoice_line) {
            $links = $invoice_line->delivery_note_lines;

            if ($links->isEmpty()) {
                continue;
            }

            $pivot_total = 0;

            foreach ($links as $dn_line) {
                $pivot_qty = (int) $dn_line->pivot->quantity;

                if ($pivot_qty <= 0) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'Delivery note link quantity must be greater than zero on invoice line '
                            . (int) $invoice_line->line_no . '.',
                        ],
                    ]);
                }

                if ((int) $dn_line->company_id !== (int) $invoice->company_id) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'Delivery note line on invoice line '
                            . (int) $invoice_line->line_no
                            . ' belongs to another company.',
                        ],
                    ]);
                }

                $delivery_note = $dn_line->delivery_note;

                if ($delivery_note === null || $delivery_note->posted_at === null) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'Delivery note must be posted before invoicing its lines (invoice line '
                            . (int) $invoice_line->line_no . ').',
                        ],
                    ]);
                }

                if (
                    $invoice_line->sales_order_line_id !== null
                    && $dn_line->sales_order_line_id !== null
                    && (int) $invoice_line->sales_order_line_id !== (int) $dn_line->sales_order_line_id
                ) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'Sales order line on invoice line '
                            . (int) $invoice_line->line_no
                            . ' does not match the linked delivery note line.',
                        ],
                    ]);
                }

                $already_invoiced = $this->invoicedQuantityOnDeliveryNoteLine(
                    (int) $dn_line->id,
                    (int) $invoice->id,
                );

                if ($already_invoiced + $pivot_qty > (int) $dn_line->quantity) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'Invoiced quantity exceeds delivered quantity on delivery note line #'
                            . (int) $dn_line->id . '.',
                        ],
                    ]);
                }

                $pivot_total += $pivot_qty;
            }

            $line_qty = (int) (float) $invoice_line->quantity;

            if ($pivot_total > $line_qty) {
                throw ValidationException::withMessages([
                    'lines' => [
                        'Sum of delivery note link quantities exceeds invoice line quantity on line '
                        . (int) $invoice_line->line_no . '.',
                    ],
                ]);
            }
        }
    }

    private function invoicedQuantityOnDeliveryNoteLine(
        int $delivery_note_line_id,
        int $excluding_invoice_id,
    ): int {
        $pivot_table = ERPTables::InvoiceLineDeliveryNoteLine->value;
        $invoice_lines_table = ERPTables::InvoiceLines->value;
        $invoices_table = ERPTables::Invoices->value;

        return (int) DB::table($pivot_table)
            ->join($invoice_lines_table, "{$invoice_lines_table}.id", '=', "{$pivot_table}.invoice_line_id")
            ->join($invoices_table, "{$invoices_table}.id", '=', "{$invoice_lines_table}.invoice_id")
            ->where("{$pivot_table}.delivery_note_line_id", $delivery_note_line_id)
            ->where("{$invoices_table}.id", '!=', $excluding_invoice_id)
            ->whereNotNull("{$invoices_table}.journal_entry_id")
            ->sum("{$pivot_table}.quantity");
    }
}
