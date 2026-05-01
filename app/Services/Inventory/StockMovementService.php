<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\StockMovementDirection;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\StockCostLayer;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\StockMovement;
use Modules\ERP\Models\Warehouse;

final class StockMovementService
{
    /**
     * Records an inbound stock movement, updates on-hand quantity, and applies
     * costing (FIFO layers or weighted average on {@see StockLevel}).
     *
     * @param  numeric-string|float|int  $unit_cost
     */
    public function recordInbound(
        int $company_id,
        int $item_id,
        int $warehouse_id,
        int $quantity,
        string|float|int $unit_cost,
        ?Model $source = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Inbound quantity must be greater than zero.'],
            ]);
        }

        $unit_cost_string = $this->normalizeMoneyString($unit_cost);

        return DB::transaction(function () use ($company_id, $item_id, $warehouse_id, $quantity, $unit_cost_string, $source): StockMovement {
            $item = $this->resolveItem($company_id, $item_id);
            $this->assertWarehouseBelongsToCompany($company_id, $warehouse_id);

            $movement = new StockMovement([
                'company_id' => $company_id,
                'item_id' => $item_id,
                'warehouse_id' => $warehouse_id,
                'direction' => StockMovementDirection::IN,
                'quantity' => $quantity,
                'unit_cost' => $unit_cost_string,
            ]);

            if ($source !== null) {
                $movement->source()->associate($source);
            }

            $movement->save();

            $stock_level = $this->lockStockLevel($company_id, $item_id, $warehouse_id);

            if ($item->costing_method === 'fifo') {
                StockCostLayer::query()->create([
                    'company_id' => $company_id,
                    'item_id' => $item_id,
                    'warehouse_id' => $warehouse_id,
                    'stock_movement_id' => $movement->id,
                    'qty_remaining' => $quantity,
                    'unit_cost' => $unit_cost_string,
                ]);

                $stock_level->quantity += $quantity;
                $this->syncFifoDisplayAverage($stock_level);
                $stock_level->save();

                return $movement;
            }

            $old_qty = (int) $stock_level->quantity;
            $old_avg = (string) $stock_level->weighted_avg_cost;
            $new_qty = $old_qty + $quantity;
            $new_avg = $new_qty > 0
                ? $this->divideDecimal(
                    $this->addDecimal(
                        $this->multiplyDecimal((string) $old_qty, $old_avg),
                        $this->multiplyDecimal((string) $quantity, $unit_cost_string),
                    ),
                    (string) $new_qty,
                )
                : '0.0000';

            $stock_level->quantity = $new_qty;
            $stock_level->weighted_avg_cost = $new_avg;
            $stock_level->save();

            return $movement;
        });
    }

    /**
     * Records an outbound movement, reducing on-hand quantity and computing
     * {@see StockMovement::$unit_cost} from FIFO layers or the weighted average.
     */
    public function recordOutbound(
        int $company_id,
        int $item_id,
        int $warehouse_id,
        int $quantity,
        ?Model $source = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Outbound quantity must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use ($company_id, $item_id, $warehouse_id, $quantity, $source): StockMovement {
            $item = $this->resolveItem($company_id, $item_id);
            $this->assertWarehouseBelongsToCompany($company_id, $warehouse_id);

            $stock_level = $this->lockStockLevel($company_id, $item_id, $warehouse_id);

            if ($stock_level->quantity < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Insufficient stock for the requested quantity.'],
                ]);
            }

            $movement = new StockMovement([
                'company_id' => $company_id,
                'item_id' => $item_id,
                'warehouse_id' => $warehouse_id,
                'direction' => StockMovementDirection::OUT,
                'quantity' => $quantity,
                'unit_cost' => null,
            ]);

            if ($source !== null) {
                $movement->source()->associate($source);
            }

            if ($item->costing_method === 'fifo') {
                $movement_unit_cost = $this->consumeFifoLayersAndComputeUnitCost(
                    $company_id,
                    $item_id,
                    $warehouse_id,
                    $quantity,
                );
                $movement->unit_cost = $movement_unit_cost;
                $movement->save();

                $stock_level->quantity -= $quantity;
                $this->syncFifoDisplayAverage($stock_level);
                $stock_level->save();

                return $movement;
            }

            $avg = (string) $stock_level->weighted_avg_cost;
            $movement->unit_cost = $avg;
            $movement->save();

            $new_qty = (int) $stock_level->quantity - $quantity;
            $stock_level->quantity = $new_qty;

            if ($new_qty === 0) {
                $stock_level->weighted_avg_cost = '0.0000';
            }

            $stock_level->save();

            return $movement;
        });
    }

    private function resolveItem(int $company_id, int $item_id): Item
    {
        /** @var Item|null $item */
        $item = Item::query()
            ->whereKey($item_id)
            ->where('company_id', $company_id)
            ->first();

        if ($item === null) {
            throw ValidationException::withMessages([
                'item_id' => ['Item not found for this company.'],
            ]);
        }

        return $item;
    }

    private function assertWarehouseBelongsToCompany(int $company_id, int $warehouse_id): void
    {
        $exists = Warehouse::query()
            ->whereKey($warehouse_id)
            ->where('company_id', $company_id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['Warehouse not found for this company.'],
            ]);
        }
    }

    private function lockStockLevel(int $company_id, int $item_id, int $warehouse_id): StockLevel
    {
        /** @var StockLevel|null $row */
        $row = StockLevel::query()
            ->where('company_id', $company_id)
            ->where('item_id', $item_id)
            ->where('warehouse_id', $warehouse_id)
            ->lockForUpdate()
            ->first();

        if ($row !== null) {
            return $row;
        }

        return StockLevel::query()->create([
            'company_id' => $company_id,
            'item_id' => $item_id,
            'warehouse_id' => $warehouse_id,
            'quantity' => 0,
            'weighted_avg_cost' => '0.0000',
        ]);
    }

    /**
     * Consumes open FIFO layers (oldest first) and returns the weighted unit
     * cost for the outbound quantity.
     */
    private function consumeFifoLayersAndComputeUnitCost(
        int $company_id,
        int $item_id,
        int $warehouse_id,
        int $quantity_out,
    ): string {
        $layers = StockCostLayer::query()
            ->where('company_id', $company_id)
            ->where('item_id', $item_id)
            ->where('warehouse_id', $warehouse_id)
            ->where('qty_remaining', '>', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $available = 0;

        foreach ($layers as $layer) {
            $available += (int) $layer->qty_remaining;
        }

        if ($available < $quantity_out) {
            throw ValidationException::withMessages([
                'quantity' => ['Insufficient stock for the requested quantity.'],
            ]);
        }

        $remaining_to_take = $quantity_out;
        $total_cost = '0.0000';

        foreach ($layers as $layer) {
            if ($remaining_to_take === 0) {
                break;
            }

            $layer_qty = (int) $layer->qty_remaining;

            if ($layer_qty === 0) {
                continue;
            }

            $take = min($layer_qty, $remaining_to_take);
            $layer_cost = $this->multiplyDecimal((string) $take, (string) $layer->unit_cost);
            $total_cost = $this->addDecimal($total_cost, $layer_cost);

            $new_remaining = $layer_qty - $take;
            $layer->qty_remaining = $new_remaining;
            $layer->save();

            $remaining_to_take -= $take;
        }

        return $this->divideDecimal($total_cost, (string) $quantity_out);
    }

    private function syncFifoDisplayAverage(StockLevel $stock_level): void
    {
        $layers = StockCostLayer::query()
            ->where('company_id', $stock_level->company_id)
            ->where('item_id', $stock_level->item_id)
            ->where('warehouse_id', $stock_level->warehouse_id)
            ->where('qty_remaining', '>', 0)
            ->get();

        if ($layers->isEmpty()) {
            $stock_level->weighted_avg_cost = '0.0000';

            return;
        }

        $layer_qty_sum = 0;
        $value = '0.0000';

        foreach ($layers as $layer) {
            $layer_qty_sum += (int) $layer->qty_remaining;
            $value = $this->addDecimal(
                $value,
                $this->multiplyDecimal((string) $layer->qty_remaining, (string) $layer->unit_cost),
            );
        }

        if ($layer_qty_sum === 0) {
            $stock_level->weighted_avg_cost = '0.0000';

            return;
        }

        $stock_level->weighted_avg_cost = $this->divideDecimal($value, (string) $layer_qty_sum);
    }

    private function normalizeMoneyString(string|float|int $value): string
    {
        $as_float = is_string($value) ? (float) $value : (float) $value;

        return $this->formatMoney4($as_float);
    }

    private function addDecimal(string $a, string $b): string
    {
        return $this->formatMoney4((float) $a + (float) $b);
    }

    private function multiplyDecimal(string $a, string $b): string
    {
        return $this->formatMoney4((float) $a * (float) $b);
    }

    private function divideDecimal(string $a, string $b): string
    {
        $den = (float) $b;

        if (abs($den) < 0.0000001) {
            return '0.0000';
        }

        return $this->formatMoney4((float) $a / $den);
    }

    private function formatMoney4(float $value): string
    {
        return number_format(round($value, 4), 4, '.', '');
    }
}
