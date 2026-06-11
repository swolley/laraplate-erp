<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Returns;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DeliveryNoteDirection;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\GoodsReceiptLine;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Models\SupplierReturn;
use Modules\ERP\Models\SupplierReturnLine;

final readonly class SupplierReturnShipmentService
{
    public function ship(SupplierReturn $supplier_return): SupplierReturn
    {
        if ($supplier_return->status !== ReturnStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => ['The supplier return must be approved before it can be processed.'],
            ]);
        }

        return DB::transaction(function () use ($supplier_return): SupplierReturn {
            $supplier_return = SupplierReturn::query()->with('lines')->lockForUpdate()->findOrFail($supplier_return->getKey());

            if ($supplier_return->status !== ReturnStatus::Approved) {
                throw ValidationException::withMessages([
                    'status' => ['The supplier return must be approved before it can be processed.'],
                ]);
            }

            if ($supplier_return->lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => ['Supplier return must have at least one line before it can be processed.'],
                ]);
            }

            $delivery_note = $this->deliveryNoteFor($supplier_return);

            if ($delivery_note->posted_at === null) {
                $delivery_note->posted_at = now();
                $delivery_note->save();
            }

            $this->registerSourceReturnedQuantities($supplier_return);

            $supplier_return->status = ReturnStatus::Processed;
            $supplier_return->processed_at = now();
            $supplier_return->delivery_note_id = (int) $delivery_note->getKey();
            $supplier_return->save();

            return $supplier_return;
        });
    }

    private function deliveryNoteFor(SupplierReturn $supplier_return): DeliveryNote
    {
        if ($supplier_return->delivery_note_id !== null) {
            return DeliveryNote::query()
                ->whereKey((int) $supplier_return->delivery_note_id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $delivery_note = DeliveryNote::query()->create([
            'company_id' => (int) $supplier_return->company_id,
            'direction' => DeliveryNoteDirection::Outbound,
            'reference' => $this->deliveryNoteReference($supplier_return),
            'delivered_at' => now(),
            'notes' => 'Generated from supplier return #' . $supplier_return->getKey(),
        ]);

        foreach ($supplier_return->lines as $line) {
            /** @var SupplierReturnLine $line */
            $delivery_note_line = $delivery_note->lines()->create([
                'company_id' => (int) $supplier_return->company_id,
                'item_id' => (int) $line->item_id,
                'warehouse_id' => (int) $line->warehouse_id,
                'quantity' => (string) $line->quantity,
            ]);

            $line->delivery_note_line_id = (int) $delivery_note_line->getKey();
            $line->save();
        }

        return $delivery_note;
    }

    private function deliveryNoteReference(SupplierReturn $supplier_return): string
    {
        if ($supplier_return->reference !== null && $supplier_return->reference !== '') {
            return 'SRET-' . mb_substr((string) $supplier_return->reference, 0, 59);
        }

        return 'SRET-' . $supplier_return->getKey();
    }

    private function registerSourceReturnedQuantities(SupplierReturn $supplier_return): void
    {
        foreach ($supplier_return->lines as $line) {
            /** @var SupplierReturnLine $line */
            $quantity = (float) $line->quantity;
            $purchase_order_line_id = $line->purchase_order_line_id === null ? null : (int) $line->purchase_order_line_id;

            if ($line->goods_receipt_line_id !== null) {
                /** @var GoodsReceiptLine $goods_receipt_line */
                $goods_receipt_line = GoodsReceiptLine::query()
                    ->whereKey((int) $line->goods_receipt_line_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $new_goods_returned = (float) $goods_receipt_line->qty_returned + $quantity;

                if ($new_goods_returned > (float) $goods_receipt_line->quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Returned quantity exceeds the source goods receipt line quantity.'],
                    ]);
                }

                $goods_receipt_line->qty_returned = $this->formatQuantity($new_goods_returned);
                $goods_receipt_line->save();

                if ($purchase_order_line_id === null && $goods_receipt_line->purchase_order_line_id !== null) {
                    $purchase_order_line_id = (int) $goods_receipt_line->purchase_order_line_id;
                }
            }

            if ($purchase_order_line_id === null) {
                continue;
            }

            /** @var PurchaseOrderLine $purchase_order_line */
            $purchase_order_line = PurchaseOrderLine::query()
                ->whereKey($purchase_order_line_id)
                ->lockForUpdate()
                ->firstOrFail();

            $new_purchase_returned = (float) $purchase_order_line->qty_returned + $quantity;

            if ($new_purchase_returned > (float) $purchase_order_line->qty_received) {
                throw ValidationException::withMessages([
                    'quantity' => ['Returned quantity exceeds the received quantity on the purchase order line.'],
                ]);
            }

            $purchase_order_line->qty_returned = $this->formatQuantity($new_purchase_returned);
            $purchase_order_line->save();
        }
    }

    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 4, '.', '');
    }
}
