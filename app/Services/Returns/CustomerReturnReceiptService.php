<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Returns;

use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DeliveryNoteDirection;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\ReturnOrder;
use Modules\ERP\Support\ConnectionScopedTransaction;
use Modules\ERP\Models\ReturnOrderLine;
use Modules\ERP\Models\SalesOrderLine;

final readonly class CustomerReturnReceiptService
{
    public function receive(ReturnOrder $return_order): ReturnOrder
    {
        if ($return_order->status !== ReturnStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => ['The return order must be approved before it can be processed.'],
            ]);
        }

        return ConnectionScopedTransaction::run($return_order, function () use ($return_order): ReturnOrder {
            $return_order = ReturnOrder::query()->with('lines')->lockForUpdate()->whereKey($return_order->id)->firstOrFail();

            if ($return_order->status !== ReturnStatus::Approved) {
                throw ValidationException::withMessages([
                    'status' => ['The return order must be approved before it can be processed.'],
                ]);
            }

            if ($return_order->lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => ['Customer return must have at least one line before it can be processed.'],
                ]);
            }

            $delivery_note = $this->deliveryNoteFor($return_order);

            if ($delivery_note->posted_at === null) {
                $delivery_note->posted_at = now();
                $delivery_note->save();
            }

            $this->registerSourceReturnedQuantities($return_order);

            $return_order->status = ReturnStatus::Processed;
            $return_order->processed_at = now();
            $return_order->delivery_note_id = $this->modelId($delivery_note);
            $return_order->save();

            return $return_order;
        });
    }

    private function deliveryNoteFor(ReturnOrder $return_order): DeliveryNote
    {
        if ($return_order->delivery_note_id !== null) {
            return DeliveryNote::query()
                ->whereKey($return_order->delivery_note_id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $delivery_note = DeliveryNote::query()->create([
            'company_id' => $return_order->company_id,
            'direction' => DeliveryNoteDirection::Inbound,
            'reference' => $this->deliveryNoteReference($return_order),
            'delivered_at' => now(),
            'notes' => 'Generated from customer return #' . (string) $return_order->id,
        ]);

        foreach ($return_order->lines as $line) {
            $delivery_note_line = $delivery_note->lines()->create([
                'company_id' => $return_order->company_id,
                'item_id' => $line->item_id,
                'warehouse_id' => $line->warehouse_id,
                'quantity' => $line->quantity,
                'sales_order_line_id' => $this->sourceSalesOrderLineId($line),
            ]);

            $line->delivery_note_line_id = $this->modelId($delivery_note_line);
            $line->save();
        }

        return $delivery_note;
    }

    private function deliveryNoteReference(ReturnOrder $return_order): string
    {
        if ($return_order->reference !== null && $return_order->reference !== '') {
            return 'RET-' . mb_substr($return_order->reference, 0, 60);
        }

        return 'RET-' . (string) $return_order->id;
    }

    private function sourceSalesOrderLineId(ReturnOrderLine $line): ?int
    {
        if ($line->invoice_line_id === null) {
            return null;
        }

        $sales_order_line_id = InvoiceLine::query()
            ->whereKey($line->invoice_line_id)
            ->value('sales_order_line_id');

        if (! is_numeric($sales_order_line_id)) {
            return null;
        }

        return (int) $sales_order_line_id;
    }

    private function registerSourceReturnedQuantities(ReturnOrder $return_order): void
    {
        foreach ($return_order->lines as $line) {
            if ($line->invoice_line_id === null) {
                continue;
            }

            $invoice_line = InvoiceLine::query()
                ->whereKey($line->invoice_line_id)
                ->lockForUpdate()
                ->firstOrFail();

            $quantity = (float) $line->quantity;
            $new_invoice_returned = (float) $invoice_line->qty_returned + $quantity;

            if ($new_invoice_returned > (float) $invoice_line->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Returned quantity exceeds the source invoice line quantity.'],
                ]);
            }

            $invoice_line->qty_returned = $this->formatQuantity($new_invoice_returned);
            $invoice_line->save();

            if ($invoice_line->sales_order_line_id === null) {
                continue;
            }

            $sales_order_line = SalesOrderLine::query()
                ->whereKey($invoice_line->sales_order_line_id)
                ->lockForUpdate()
                ->firstOrFail();

            $new_sales_returned = (float) $sales_order_line->qty_returned + $quantity;

            if ($new_sales_returned > (float) $sales_order_line->qty_invoiced) {
                throw ValidationException::withMessages([
                    'quantity' => ['Returned quantity exceeds the invoiced quantity on the sales order line.'],
                ]);
            }

            $sales_order_line->qty_returned = $this->formatQuantity($new_sales_returned);
            $sales_order_line->save();
        }
    }

    /**
     * @return numeric-string
     */
    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 4, '.', '');
    }

    private function modelId(DeliveryNote|DeliveryNoteLine $model): int
    {
        return is_int($model->id) ? $model->id : (int) $model->id;
    }
}
