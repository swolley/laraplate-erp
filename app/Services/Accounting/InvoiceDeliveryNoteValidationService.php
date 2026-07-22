<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Support\ConnectionScopedTransaction;

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

            $pivot_total = 0.0;

            foreach ($links as $dn_line) {
                $pivot_qty = $this->pivotQuantity($dn_line, $invoice_line->line_no);

                if ($pivot_qty <= 0.0) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'Delivery note link quantity must be greater than zero on invoice line '
                            . $invoice_line->line_no . '.',
                        ],
                    ]);
                }

                if ($dn_line->company_id !== $invoice->company_id) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'Delivery note line on invoice line '
                            . $invoice_line->line_no
                            . ' belongs to another company.',
                        ],
                    ]);
                }

                $delivery_note = $dn_line->delivery_note;

                if ($delivery_note === null || $delivery_note->posted_at === null) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'Delivery note must be posted before invoicing its lines (invoice line '
                            . $invoice_line->line_no . ').',
                        ],
                    ]);
                }

                if (
                    $invoice_line->sales_order_line_id !== null
                    && $dn_line->sales_order_line_id !== null
                    && $invoice_line->sales_order_line_id !== $dn_line->sales_order_line_id
                ) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'Sales order line on invoice line '
                            . $invoice_line->line_no
                            . ' does not match the linked delivery note line.',
                        ],
                    ]);
                }

                $already_invoiced = $this->invoicedQuantityOnDeliveryNoteLine(
                    $dn_line,
                    $invoice,
                );

                if ($already_invoiced + $pivot_qty > (float) $dn_line->quantity) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'Invoiced quantity exceeds delivered quantity on delivery note line #'
                            . (string) $dn_line->id . '.',
                        ],
                    ]);
                }

                $pivot_total += $pivot_qty;
            }

            $line_qty = (float) $invoice_line->quantity;

            if ($pivot_total > $line_qty) {
                throw ValidationException::withMessages([
                    'lines' => [
                        'Sum of delivery note link quantities exceeds invoice line quantity on line '
                        . $invoice_line->line_no . '.',
                    ],
                ]);
            }
        }
    }

    private function modelId(DeliveryNoteLine|Invoice $model): int
    {
        return is_int($model->id) ? $model->id : (int) $model->id;
    }

    private function pivotQuantity(DeliveryNoteLine $dn_line, int $invoice_line_no): float
    {
        $pivot = $dn_line->pivot;

        if ($pivot === null) {
            throw ValidationException::withMessages([
                'lines' => [
                    'Delivery note link is missing pivot data on invoice line ' . $invoice_line_no . '.',
                ],
            ]);
        }

        $quantity = $pivot->getAttributes()['quantity'] ?? null;

        if (! is_numeric($quantity)) {
            throw ValidationException::withMessages([
                'lines' => [
                    'Delivery note link quantity must be greater than zero on invoice line '
                    . $invoice_line_no . '.',
                ],
            ]);
        }

        return (float) $quantity;
    }

    private function invoicedQuantityOnDeliveryNoteLine(DeliveryNoteLine $delivery_note_line, Invoice $excluding_invoice): float
    {
        ConnectionScopedTransaction::connection($excluding_invoice, $delivery_note_line);

        $pivot_table = ERPTables::InvoiceLineDeliveryNoteLine->value;
        $invoice_lines_table = (new InvoiceLine)->getTable();
        $invoices_table = $excluding_invoice->getTable();

        return (float) $excluding_invoice->getConnection()->table($pivot_table)
            ->join($invoice_lines_table, "{$invoice_lines_table}.id", '=', "{$pivot_table}.invoice_line_id")
            ->join($invoices_table, "{$invoices_table}.id", '=', "{$invoice_lines_table}.invoice_id")
            ->where("{$pivot_table}.delivery_note_line_id", $this->modelId($delivery_note_line))
            ->where("{$invoices_table}.id", '!=', $this->modelId($excluding_invoice))
            ->whereNotNull("{$invoices_table}.journal_entry_id")
            ->sum("{$pivot_table}.quantity");
    }
}
