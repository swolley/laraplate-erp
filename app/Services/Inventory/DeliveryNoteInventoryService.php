<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Services\SalesOrders\SalesOrderEvasionService;

/**
 * Posts inventory for a {@see DeliveryNote} after `posted_at` is set: outbound
 * {@see StockMovement} rows (sourced from {@see DeliveryNoteLine}) and optional
 * {@see SalesOrderEvasionService::registerDelivery} when the note is linked to a SO.
 */
final class DeliveryNoteInventoryService
{
    public function __construct(
        private readonly StockMovementService $stock_movement_service,
        private readonly SalesOrderEvasionService $sales_order_evasion_service,
    ) {}

    /**
     * Idempotent when {@see DeliveryNote::$inventory_posted_at} is already set.
     *
     * @throws ValidationException When header/lines are inconsistent with stock or SO.
     */
    public function postInventory(DeliveryNote $note): void
    {
        DB::transaction(function () use ($note): void {
            /** @var DeliveryNote $locked */
            $locked = DeliveryNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();

            if ($locked->inventory_posted_at !== null) {
                return;
            }

            if ($note->posted_at === null) {
                throw ValidationException::withMessages([
                    'posted_at' => ['posted_at must be set before inventory posting.'],
                ]);
            }

            $lines = DeliveryNoteLine::query()
                ->where('delivery_note_id', $note->id)
                ->orderBy('id')
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => ['Delivery note must have at least one line before inventory posting.'],
                ]);
            }

            $this->validateSalesOrderLines($locked, $lines);

            foreach ($lines as $line) {
                $this->stock_movement_service->recordOutbound(
                    (int) $locked->company_id,
                    (int) $line->item_id,
                    (int) $line->warehouse_id,
                    (int) $line->quantity,
                    $line,
                );
            }

            if ($locked->sales_order_id !== null) {
                $quantities = [];

                foreach ($lines as $line) {
                    if ($line->sales_order_line_id === null) {
                        continue;
                    }

                    $line_id = (int) $line->sales_order_line_id;
                    $quantities[$line_id] = ($quantities[$line_id] ?? 0) + (int) $line->quantity;
                }

                if ($quantities !== []) {
                    $sales_order = SalesOrder::query()
                        ->whereKey((int) $locked->sales_order_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->sales_order_evasion_service->registerDelivery($sales_order, $quantities);
                }
            }

            $note->inventory_posted_at = now();
        });
    }

    /**
     * @param  Collection<int, DeliveryNoteLine>  $lines
     */
    private function validateSalesOrderLines(DeliveryNote $header, Collection $lines): void
    {
        if ($header->sales_order_id === null) {
            return;
        }

        $sales_order_id = (int) $header->sales_order_id;

        foreach ($lines as $line) {
            if ($line->sales_order_line_id === null) {
                continue;
            }

            /** @var SalesOrderLine|null $so_line */
            $so_line = SalesOrderLine::query()->find($line->sales_order_line_id);

            if ($so_line === null) {
                throw ValidationException::withMessages([
                    'sales_order_line_id' => ['Referenced sales order line does not exist.'],
                ]);
            }

            if ((int) $so_line->sales_order_id !== $sales_order_id) {
                throw ValidationException::withMessages([
                    'sales_order_line_id' => ['Sales order line does not belong to the delivery note sales order.'],
                ]);
            }

            $remaining = (int) $so_line->qty_ordered - (int) $so_line->qty_delivered;

            if ((int) $line->quantity > $remaining) {
                throw ValidationException::withMessages([
                    'quantity' => ['Delivery quantity exceeds remaining quantity to deliver on the sales order line.'],
                ]);
            }
        }
    }
}
