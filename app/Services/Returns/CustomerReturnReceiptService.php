<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Returns;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DeliveryNoteDirection;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\ReturnOrder;
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

        return DB::transaction(function () use ($return_order): ReturnOrder {
            $return_order = ReturnOrder::query()->with('lines')->lockForUpdate()->findOrFail($return_order->getKey());

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
            $return_order->delivery_note_id = (int) $delivery_note->getKey();
            $return_order->save();

            return $return_order;
        });
    }

    private function deliveryNoteFor(ReturnOrder $return_order): DeliveryNote
    {
        if ($return_order->delivery_note_id !== null) {
            return DeliveryNote::query()
                ->whereKey((int) $return_order->delivery_note_id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $delivery_note = DeliveryNote::query()->create([
            'company_id' => (int) $return_order->company_id,
            'direction' => DeliveryNoteDirection::Inbound,
            'reference' => $this->deliveryNoteReference($return_order),
            'delivered_at' => now(),
            'notes' => 'Generated from customer return #' . $return_order->getKey(),
        ]);

        foreach ($return_order->lines as $line) {
            /** @var ReturnOrderLine $line */
            $delivery_note_line = $delivery_note->lines()->create([
                'company_id' => (int) $return_order->company_id,
                'item_id' => (int) $line->item_id,
                'warehouse_id' => (int) $line->warehouse_id,
                'quantity' => (string) $line->quantity,
                'sales_order_line_id' => $this->sourceSalesOrderLineId($line),
            ]);

            $line->delivery_note_line_id = (int) $delivery_note_line->getKey();
            $line->save();
        }

        return $delivery_note;
    }

    private function deliveryNoteReference(ReturnOrder $return_order): string
    {
        if ($return_order->reference !== null && $return_order->reference !== '') {
            return 'RET-' . mb_substr((string) $return_order->reference, 0, 60);
        }

        return 'RET-' . $return_order->getKey();
    }

    private function sourceSalesOrderLineId(ReturnOrderLine $line): ?int
    {
        if ($line->invoice_line_id === null) {
            return null;
        }

        $sales_order_line_id = InvoiceLine::query()
            ->whereKey((int) $line->invoice_line_id)
            ->value('sales_order_line_id');

        return $sales_order_line_id === null ? null : (int) $sales_order_line_id;
    }

    private function registerSourceReturnedQuantities(ReturnOrder $return_order): void
    {
        foreach ($return_order->lines as $line) {
            /** @var ReturnOrderLine $line */
            if ($line->invoice_line_id === null) {
                continue;
            }

            /** @var InvoiceLine $invoice_line */
            $invoice_line = InvoiceLine::query()
                ->whereKey((int) $line->invoice_line_id)
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

            /** @var SalesOrderLine $sales_order_line */
            $sales_order_line = SalesOrderLine::query()
                ->whereKey((int) $invoice_line->sales_order_line_id)
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

    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 4, '.', '');
    }
}
