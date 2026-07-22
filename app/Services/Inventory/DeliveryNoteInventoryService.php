<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DeliveryNoteDirection;
use Modules\ERP\Casts\StockMovementDirection;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Support\ConnectionScopedTransaction;
use Modules\ERP\Support\ConnectionScopedModels;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\ReturnOrderLine;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Models\StockMovement;
use Modules\ERP\Models\SupplierReturnLine;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\SalesOrders\SalesOrderEvasionService;

/**
 * Posts inventory for a {@see DeliveryNote} after `posted_at` is set: outbound
 * notes reduce stock and can update SO evasion, while inbound notes restore stock.
 */
final readonly class DeliveryNoteInventoryService
{
    public function __construct(
        private StockMovementService $stock_movement_service,
        private SalesOrderEvasionService $sales_order_evasion_service,
        private DeliveryNoteCogsJournalService $delivery_note_cogs_journal_service,
    ) {}

    /**
     * Idempotent when {@see DeliveryNote::$inventory_posted_at} is already set.
     *
     * @throws ValidationException When header/lines are inconsistent with stock or SO.
     */
    public function postInventory(DeliveryNote $note): void
    {
        ConnectionScopedTransaction::run($note, function (ConnectionScopedModels $models) use ($note): void {
            /** @var DeliveryNote $locked */
            $locked = $models->query(DeliveryNote::class)->whereKey($note->id)->lockForUpdate()->firstOrFail();

            if ($locked->inventory_posted_at !== null) {
                return;
            }

            if ($note->posted_at === null) {
                throw ValidationException::withMessages([
                    'posted_at' => ['posted_at must be set before inventory posting.'],
                ]);
            }

            $lines = $models->query(DeliveryNoteLine::class)
                ->where('delivery_note_id', $note->id)
                ->orderBy('id')
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => ['Delivery note must have at least one line before inventory posting.'],
                ]);
            }

            $this->validateLines($models, $locked, $lines);

            if ($locked->direction === DeliveryNoteDirection::Inbound) {
                $this->postInboundMovements($models, $locked, $lines);
                $note->inventory_posted_at = now();

                return;
            }

            foreach ($lines as $line) {
                $this->stock_movement_service->recordOutbound(
                    $locked->company_id,
                    $line->item_id,
                    $line->warehouse_id,
                    $line->quantity,
                    $line,
                );
            }

            if (! $this->isSupplierReturnDeliveryNote($models, $lines)) {
                $this->delivery_note_cogs_journal_service->postForDeliveryNoteIfNeeded($note, $lines);
            }

            if ($locked->sales_order_id !== null) {
                /** @var array<int, numeric-string> $quantities */
                $quantities = [];

                foreach ($lines as $line) {
                    if ($line->sales_order_line_id === null) {
                        continue;
                    }

                    $line_id = $line->sales_order_line_id;
                    $quantities[$line_id] = $this->addQuantity($quantities[$line_id] ?? '0.0000', $line->quantity);
                }

                if ($quantities !== []) {
                    $sales_order = $models->query(SalesOrder::class)
                        ->whereKey($locked->sales_order_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->sales_order_evasion_service->registerDelivery($sales_order, $quantities);
                }
            }

            $note->inventory_posted_at = now();
        });
    }

    /**
     * Reverts inventory effects for a previously posted delivery note.
     *
     * @throws ValidationException When original outbound movements cannot be matched.
     */
    public function unpostInventory(DeliveryNote $note): void
    {
        ConnectionScopedTransaction::run($note, function (ConnectionScopedModels $models) use ($note): void {
            /** @var DeliveryNote $locked */
            $locked = $models->query(DeliveryNote::class)->whereKey($note->id)->lockForUpdate()->firstOrFail();

            if ($locked->inventory_posted_at === null) {
                return;
            }

            $lines = $models->query(DeliveryNoteLine::class)
                ->where('delivery_note_id', $note->id)
                ->orderBy('id')
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => ['Delivery note must have at least one line before inventory unposting.'],
                ]);
            }

            if ($locked->direction === DeliveryNoteDirection::Inbound) {
                $this->revertInboundMovements($models, $locked, $lines);
                $note->inventory_posted_at = null;

                return;
            }

            $this->revertOutboundMovements($models, $locked, $lines);
            $this->delivery_note_cogs_journal_service->reverseForDeliveryNoteIfNeeded($note);

            if ($locked->sales_order_id !== null) {
                /** @var array<int, numeric-string> $quantities */
                $quantities = [];

                foreach ($lines as $line) {
                    if ($line->sales_order_line_id === null) {
                        continue;
                    }

                    $line_id = $line->sales_order_line_id;
                    $quantities[$line_id] = $this->addQuantity($quantities[$line_id] ?? '0.0000', $line->quantity);
                }

                if ($quantities !== []) {
                    $sales_order = $models->query(SalesOrder::class)
                        ->whereKey($locked->sales_order_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->sales_order_evasion_service->unregisterDelivery($sales_order, $quantities);
                }
            }

            $note->inventory_posted_at = null;
        });
    }

    /**
     * @param  Collection<int, DeliveryNoteLine>  $lines
     */
    private function validateLines(ConnectionScopedModels $models, DeliveryNote $header, Collection $lines): void
    {
        $company_id = $header->company_id;

        foreach ($lines as $line) {
            if ($company_id !== $line->company_id) {
                throw ValidationException::withMessages([
                    'company_id' => ['Delivery note line company does not match delivery note company.'],
                ]);
            }

            $item_matches_company = $models->query(Item::class)
                ->whereKey($line->item_id)
                ->where('company_id', $company_id)
                ->exists();

            if (! $item_matches_company) {
                throw ValidationException::withMessages([
                    'item_id' => ['Item does not belong to the same company as the delivery note.'],
                ]);
            }

            $warehouse_matches_company = $models->query(Warehouse::class)
                ->whereKey($line->warehouse_id)
                ->where('company_id', $company_id)
                ->exists();

            if (! $warehouse_matches_company) {
                throw ValidationException::withMessages([
                    'warehouse_id' => ['Warehouse does not belong to the same company as the delivery note.'],
                ]);
            }
        }

        if ($header->sales_order_id === null) {
            return;
        }

        if ($header->direction === DeliveryNoteDirection::Inbound) {
            return;
        }

        $sales_order_id = $header->sales_order_id;

        foreach ($lines as $line) {
            if ($line->sales_order_line_id === null) {
                continue;
            }

            /** @var SalesOrderLine|null $so_line */
            $so_line = $models->query(SalesOrderLine::class)->find($line->sales_order_line_id);

            if ($so_line === null) {
                throw ValidationException::withMessages([
                    'sales_order_line_id' => ['Referenced sales order line does not exist.'],
                ]);
            }

            if ($sales_order_id !== $so_line->sales_order_id) {
                throw ValidationException::withMessages([
                    'sales_order_line_id' => ['Sales order line does not belong to the delivery note sales order.'],
                ]);
            }

            $remaining = (float) $so_line->qty_ordered - (float) $so_line->qty_delivered;

            if ($remaining < (float) $line->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Delivery quantity exceeds remaining quantity to deliver on the sales order line.'],
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, DeliveryNoteLine>  $lines
     */
    private function postInboundMovements(ConnectionScopedModels $models, DeliveryNote $header, Collection $lines): void
    {
        foreach ($lines as $line) {
            $this->stock_movement_service->recordInbound(
                $header->company_id,
                $line->item_id,
                $line->warehouse_id,
                $line->quantity,
                $this->resolveReturnedUnitCost($models, $header, $line),
                $line,
            );
        }
    }

    /**
     * @param  Collection<int, DeliveryNoteLine>  $lines
     */
    private function revertOutboundMovements(ConnectionScopedModels $models, DeliveryNote $header, Collection $lines): void
    {
        $line_ids = $lines->pluck('id')->all();
        $outbound_movements = $models->query(StockMovement::class)
            ->where('company_id', $header->company_id)
            ->where('source_type', (new DeliveryNoteLine)->getMorphClass())
            ->whereIn('source_id', $line_ids)
            ->where('direction', StockMovementDirection::Out)
            ->get()
            ->keyBy(static fn (StockMovement $movement): int => $movement->source_id);

        foreach ($lines as $line) {
            /** @var StockMovement|null $outbound */
            $outbound = $outbound_movements->get($line->id);

            if ($outbound === null || $outbound->unit_cost === null) {
                throw ValidationException::withMessages([
                    'stock' => ['Cannot unpost delivery note: missing outbound movement or unit_cost for a line.'],
                ]);
            }

            $this->stock_movement_service->recordInbound(
                $header->company_id,
                $line->item_id,
                $line->warehouse_id,
                $line->quantity,
                $outbound->unit_cost,
                $line,
            );
        }
    }

    /**
     * @param  Collection<int, DeliveryNoteLine>  $lines
     */
    private function revertInboundMovements(ConnectionScopedModels $models, DeliveryNote $header, Collection $lines): void
    {
        $line_ids = $lines->pluck('id')->all();
        $inbound_movements = $models->query(StockMovement::class)
            ->where('company_id', $header->company_id)
            ->where('source_type', (new DeliveryNoteLine)->getMorphClass())
            ->whereIn('source_id', $line_ids)
            ->where('direction', StockMovementDirection::In)
            ->get()
            ->keyBy(static fn (StockMovement $movement): int => $movement->source_id);

        foreach ($lines as $line) {
            /** @var StockMovement|null $inbound */
            $inbound = $inbound_movements->get($line->id);

            if ($inbound === null) {
                throw ValidationException::withMessages([
                    'stock' => ['Cannot unpost delivery note: missing inbound movement for a line.'],
                ]);
            }

            $this->stock_movement_service->recordOutbound(
                $header->company_id,
                $line->item_id,
                $line->warehouse_id,
                $line->quantity,
                $line,
            );
        }
    }

    /**
     * @return numeric-string
     */
    private function resolveReturnedUnitCost(ConnectionScopedModels $models, DeliveryNote $header, DeliveryNoteLine $line): string
    {
        if ($line->sales_order_line_id !== null) {
            return $this->resolveReturnedUnitCostFromOutboundMovement($models, $header, $line);
        }

        if ($models->query(ReturnOrderLine::class)
            ->where('delivery_note_line_id', $line->getKey())
            ->where('company_id', $header->company_id)
            ->exists()) {
            return $this->resolveReturnedUnitCostFromReturnLine($models, $header, $line);
        }

        throw ValidationException::withMessages([
            'sales_order_line_id' => ['Inbound delivery note lines require a source sales order line or return order line to resolve inventory cost.'],
        ]);
    }

    /**
     * @return numeric-string
     */
    private function resolveReturnedUnitCostFromOutboundMovement(ConnectionScopedModels $models, DeliveryNote $header, DeliveryNoteLine $line): string
    {
        $source_line_ids = $models->query(DeliveryNoteLine::class)
            ->where('company_id', $header->company_id)
            ->where('sales_order_line_id', $line->sales_order_line_id)
            ->where('item_id', $line->item_id)
            ->pluck('id')
            ->all();

        /** @var StockMovement|null $movement */
        $movement = $models->query(StockMovement::class)
            ->where('company_id', $header->company_id)
            ->where('item_id', $line->item_id)
            ->where('source_type', (new DeliveryNoteLine)->getMorphClass())
            ->whereIn('source_id', $source_line_ids)
            ->where('direction', StockMovementDirection::Out)
            ->whereNotNull('unit_cost')
            ->orderByDesc('id')
            ->first();

        if ($movement === null || $movement->unit_cost === null) {
            throw ValidationException::withMessages([
                'stock' => ['Cannot post inbound delivery note: missing original outbound stock cost for the returned line.'],
            ]);
        }

        return $movement->unit_cost;
    }

    /**
     * @return numeric-string
     */
    private function resolveReturnedUnitCostFromReturnLine(ConnectionScopedModels $models, DeliveryNote $header, DeliveryNoteLine $line): string
    {
        /** @var ReturnOrderLine|null $return_line */
        $return_line = $models->query(ReturnOrderLine::class)
            ->where('delivery_note_line_id', $line->getKey())
            ->where('company_id', $header->company_id)
            ->first();

        if ($return_line === null || $return_line->unit_cost === null) {
            throw ValidationException::withMessages([
                'unit_cost' => ['Inbound return delivery note line requires a linked customer return line with unit cost.'],
            ]);
        }

        return $return_line->unit_cost;
    }

    /**
     * @param  Collection<int, DeliveryNoteLine>  $lines
     */
    private function isSupplierReturnDeliveryNote(ConnectionScopedModels $models, Collection $lines): bool
    {
        return $models->query(SupplierReturnLine::class)
            ->whereIn('delivery_note_line_id', $lines->pluck('id')->all())
            ->exists();
    }

    /**
     * @param  numeric-string  $left
     * @param  numeric-string  $right
     * @return numeric-string
     */
    private function addQuantity(string $left, string $right): string
    {
        return number_format((float) $left + (float) $right, 4, '.', '');
    }
}
