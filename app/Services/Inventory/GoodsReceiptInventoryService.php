<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Models\GoodsReceipt;
use Modules\ERP\Models\GoodsReceiptLine;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Models\Warehouse;

/**
 * Posts inventory for a {@see GoodsReceipt} after `posted_at` is set: inbound
 * {@see StockMovement} rows (sourced from {@see GoodsReceiptLine}) and optional
 * purchase order line receipt quantities when the receipt is linked to a PO.
 */
final class GoodsReceiptInventoryService
{
    public function __construct(
        private readonly StockMovementService $stock_movement_service,
    ) {}

    /**
     * Idempotent when {@see GoodsReceipt::$inventory_posted_at} is already set.
     *
     * @throws ValidationException When header/lines are inconsistent with stock or PO.
     */
    public function postInventory(GoodsReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt): void {
            /** @var GoodsReceipt $locked */
            $locked = GoodsReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

            if ($locked->inventory_posted_at !== null) {
                return;
            }

            if ($receipt->posted_at === null) {
                throw ValidationException::withMessages([
                    'posted_at' => ['posted_at must be set before inventory posting.'],
                ]);
            }

            $lines = GoodsReceiptLine::query()
                ->where('goods_receipt_id', $receipt->id)
                ->orderBy('id')
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => ['Goods receipt must have at least one line before inventory posting.'],
                ]);
            }

            $this->validatePurchaseOrderLines($locked, $lines);

            foreach ($lines as $line) {
                $this->stock_movement_service->recordInbound(
                    (int) $locked->company_id,
                    (int) $line->item_id,
                    (int) $line->warehouse_id,
                    (int) $line->quantity,
                    (string) $line->unit_cost,
                    $line,
                );
            }

            if ($locked->purchase_order_id !== null) {
                $quantities = [];

                foreach ($lines as $line) {
                    if ($line->purchase_order_line_id === null) {
                        continue;
                    }

                    $po_line_id = (int) $line->purchase_order_line_id;
                    $quantities[$po_line_id] = ($quantities[$po_line_id] ?? 0) + (int) $line->quantity;
                }

                if ($quantities !== []) {
                    $purchase_order = PurchaseOrder::query()
                        ->whereKey((int) $locked->purchase_order_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->registerPurchaseReceipts($purchase_order, $quantities);
                    $this->syncPurchaseOrderStatus($purchase_order->fresh(['lines']));
                }
            }

            $receipt->inventory_posted_at = now();
        });
    }

    /**
     * @param  Collection<int, GoodsReceiptLine>  $lines
     */
    private function validatePurchaseOrderLines(GoodsReceipt $header, Collection $lines): void
    {
        $company_id = (int) $header->company_id;

        foreach ($lines as $line) {
            if ((int) $line->company_id !== $company_id) {
                throw ValidationException::withMessages([
                    'company_id' => ['Goods receipt line company does not match goods receipt company.'],
                ]);
            }

            $item_matches_company = Item::query()
                ->whereKey((int) $line->item_id)
                ->where('company_id', $company_id)
                ->exists();

            if (! $item_matches_company) {
                throw ValidationException::withMessages([
                    'item_id' => ['Item does not belong to the same company as the goods receipt.'],
                ]);
            }

            $warehouse_matches_company = Warehouse::query()
                ->whereKey((int) $line->warehouse_id)
                ->where('company_id', $company_id)
                ->exists();

            if (! $warehouse_matches_company) {
                throw ValidationException::withMessages([
                    'warehouse_id' => ['Warehouse does not belong to the same company as the goods receipt.'],
                ]);
            }
        }

        if ($header->purchase_order_id === null) {
            return;
        }

        $purchase_order_id = (int) $header->purchase_order_id;

        /** @var PurchaseOrder|null $po */
        $po = PurchaseOrder::query()->find($purchase_order_id);

        if ($po === null) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['Purchase order not found.'],
            ]);
        }

        if ((int) $po->company_id !== (int) $header->company_id) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['Purchase order does not belong to the same company as the goods receipt.'],
            ]);
        }

        foreach ($lines as $line) {
            if ($line->purchase_order_line_id === null) {
                continue;
            }

            /** @var PurchaseOrderLine|null $po_line */
            $po_line = PurchaseOrderLine::query()->find($line->purchase_order_line_id);

            if ($po_line === null) {
                throw ValidationException::withMessages([
                    'purchase_order_line_id' => ['Referenced purchase order line does not exist.'],
                ]);
            }

            if ((int) $po_line->purchase_order_id !== $purchase_order_id) {
                throw ValidationException::withMessages([
                    'purchase_order_line_id' => ['Purchase order line does not belong to the goods receipt purchase order.'],
                ]);
            }

            $remaining = (int) $po_line->qty_ordered - (int) $po_line->qty_received;

            if ((int) $line->quantity > $remaining) {
                throw ValidationException::withMessages([
                    'quantity' => ['Receipt quantity exceeds remaining quantity to receive on the purchase order line.'],
                ]);
            }
        }
    }

    /**
     * @param  array<int, int>  $line_quantities
     */
    private function registerPurchaseReceipts(PurchaseOrder $purchase_order, array $line_quantities): void
    {
        foreach ($line_quantities as $line_id => $qty) {
            if ($qty <= 0) {
                continue;
            }

            /** @var PurchaseOrderLine|null $po_line */
            $po_line = $purchase_order->lines()
                ->whereKey($line_id)
                ->lockForUpdate()
                ->first();

            if ($po_line === null) {
                continue;
            }

            $po_line->qty_received = min(
                (int) $po_line->qty_ordered,
                (int) $po_line->qty_received + $qty,
            );
            $po_line->save();
        }
    }

    private function syncPurchaseOrderStatus(PurchaseOrder $purchase_order): void
    {
        $lines = $purchase_order->lines;

        if ($lines->isEmpty()) {
            return;
        }

        $all_received = $lines->every(
            static fn (PurchaseOrderLine $line): bool => $line->qty_received >= $line->qty_ordered
        );

        if ($all_received) {
            $purchase_order->status = 'received';
            $purchase_order->saveQuietly();

            return;
        }

        $has_progress = $lines->contains(
            static fn (PurchaseOrderLine $line): bool => $line->qty_received > 0
        );

        if ($has_progress) {
            $purchase_order->status = 'partial';
            $purchase_order->saveQuietly();
        }
    }
}
