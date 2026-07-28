<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Returns;

use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DeliveryNoteDirection;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\GoodsReceiptLine;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Models\SupplierReturn;
use Modules\ERP\Models\SupplierReturnLine;
use Modules\ERP\Support\ConnectionScopedModels;
use Modules\ERP\Support\ConnectionScopedTransaction;

final readonly class SupplierReturnShipmentService
{
    public function ship(SupplierReturn $supplier_return): SupplierReturn
    {
        if ($supplier_return->status !== ReturnStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => ['The supplier return must be approved before it can be processed.'],
            ]);
        }

        return ConnectionScopedTransaction::run($supplier_return, function (ConnectionScopedModels $models) use ($supplier_return): SupplierReturn {
            $models->model(DeliveryNote::class);
            $models->model(DeliveryNoteLine::class);
            $models->model(GoodsReceiptLine::class);
            $models->model(PurchaseOrderLine::class);
            $models->model(SupplierReturnLine::class);

            $supplier_return = $models->query(SupplierReturn::class)->with('lines')->lockForUpdate()->whereKey($supplier_return->id)->firstOrFail();

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

            $delivery_note = $this->deliveryNoteFor($models, $supplier_return);

            if ($delivery_note->posted_at === null) {
                $delivery_note->posted_at = now();
                $delivery_note->save();
            }

            $this->registerSourceReturnedQuantities($models, $supplier_return);

            $supplier_return->status = ReturnStatus::Processed;
            $supplier_return->processed_at = now();
            $supplier_return->delivery_note_id = $this->modelId($delivery_note);
            $supplier_return->save();

            return $supplier_return;
        });
    }

    private function deliveryNoteFor(ConnectionScopedModels $models, SupplierReturn $supplier_return): DeliveryNote
    {
        if ($supplier_return->delivery_note_id !== null) {
            return $models->query(DeliveryNote::class)
                ->whereKey($supplier_return->delivery_note_id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $delivery_note = $models->query(DeliveryNote::class)->create([
            'company_id' => $supplier_return->company_id,
            'direction' => DeliveryNoteDirection::Outbound,
            'reference' => $this->deliveryNoteReference($supplier_return),
            'delivered_at' => now(),
            'notes' => 'Generated from supplier return #' . (string) $supplier_return->id,
        ]);

        foreach ($supplier_return->lines as $line) {
            $delivery_note_line = $delivery_note->lines()->create([
                'company_id' => $supplier_return->company_id,
                'item_id' => $line->item_id,
                'warehouse_id' => $line->warehouse_id,
                'quantity' => $line->quantity,
            ]);

            $line->delivery_note_line_id = $this->modelId($delivery_note_line);
            $line->save();
        }

        return $delivery_note;
    }

    private function deliveryNoteReference(SupplierReturn $supplier_return): string
    {
        if ($supplier_return->reference !== null && $supplier_return->reference !== '') {
            return 'SRET-' . mb_substr($supplier_return->reference, 0, 59);
        }

        return 'SRET-' . (string) $supplier_return->id;
    }

    private function registerSourceReturnedQuantities(ConnectionScopedModels $models, SupplierReturn $supplier_return): void
    {
        foreach ($supplier_return->lines as $line) {
            $quantity = (float) $line->quantity;
            $purchase_order_line_id = $line->purchase_order_line_id;

            if ($line->goods_receipt_line_id !== null) {
                $goods_receipt_line = $models->query(GoodsReceiptLine::class)
                    ->whereKey($line->goods_receipt_line_id)
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
                    $purchase_order_line_id = $goods_receipt_line->purchase_order_line_id;
                }
            }

            if ($purchase_order_line_id === null) {
                continue;
            }

            $purchase_order_line = $models->query(PurchaseOrderLine::class)
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
