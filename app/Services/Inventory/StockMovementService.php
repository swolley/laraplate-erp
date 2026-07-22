<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\StockMovementDirection;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\StockCostLayer;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\StockMovement;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Support\ConnectionScopedTransaction;
use Modules\ERP\Support\ConnectionScopedModels;

final class StockMovementService
{
    /**
     * Records an inbound stock movement, updates on-hand quantity, and applies
     * costing (FIFO layers or weighted average on {@see StockLevel}).
     *
     * @param  numeric-string|float|int  $quantity
     * @param  numeric-string|float|int  $unit_cost
     */
    public function recordInbound(
        int $company_id,
        int $item_id,
        int $warehouse_id,
        string|float|int $quantity,
        string|float|int $unit_cost,
        ?Model $source = null,
    ): StockMovement {
        $quantity_string = $this->normalizeQuantityString($quantity);

        if ((float) $quantity_string <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Inbound quantity must be greater than zero.'],
            ]);
        }

        $unit_cost_string = $this->normalizeMoneyString($unit_cost);
        $company = $source instanceof Model
            ? ConnectionScopedModels::for($source)->query(Company::class)->findOrFail($company_id)
            : Company::query()->findOrFail($company_id);

        if ($source instanceof Model) {
            ConnectionScopedTransaction::connection($company, $source);
        }

        return ConnectionScopedTransaction::run($company, function (ConnectionScopedModels $models) use ($company_id, $item_id, $warehouse_id, $quantity_string, $unit_cost_string, $source): StockMovement {
            $item = $this->resolveItem($models, $company_id, $item_id);
            $this->assertWarehouseBelongsToCompany($models, $company_id, $warehouse_id);

            $movement = $models->model(StockMovement::class)->fill([
                'company_id' => $company_id,
                'item_id' => $item_id,
                'warehouse_id' => $warehouse_id,
                'direction' => StockMovementDirection::In,
                'quantity' => $quantity_string,
                'unit_cost' => $unit_cost_string,
            ]);

            if ($source instanceof Model) {
                $movement->source()->associate($source);
            }

            $movement->save();

            $stock_level = $this->lockStockLevel($models, $company_id, $item_id, $warehouse_id);

            if ($item->costing_method === 'fifo') {
                $models->query(StockCostLayer::class)->create([
                    'company_id' => $company_id,
                    'item_id' => $item_id,
                    'warehouse_id' => $warehouse_id,
                    'stock_movement_id' => $movement->id,
                    'qty_remaining' => $quantity_string,
                    'unit_cost' => $unit_cost_string,
                ]);

                $stock_level->quantity = $this->addDecimal($stock_level->quantity, $quantity_string);
                $this->syncFifoDisplayAverage($models, $stock_level);
                $stock_level->save();

                return $movement;
            }

            $old_qty = $stock_level->quantity;
            $old_avg = $stock_level->weighted_avg_cost;
            $new_qty = $this->addDecimal($old_qty, $quantity_string);
            $new_avg = (float) $new_qty > 0
                ? $this->divideDecimal(
                    $this->addDecimal(
                        $this->multiplyDecimal($old_qty, $old_avg),
                        $this->multiplyDecimal($quantity_string, $unit_cost_string),
                    ),
                    $new_qty,
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
     *
     * @param  numeric-string|float|int  $quantity
     */
    public function recordOutbound(
        int $company_id,
        int $item_id,
        int $warehouse_id,
        string|float|int $quantity,
        ?Model $source = null,
    ): StockMovement {
        $quantity_string = $this->normalizeQuantityString($quantity);

        if ((float) $quantity_string <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Outbound quantity must be greater than zero.'],
            ]);
        }
        $company = $source instanceof Model
            ? ConnectionScopedModels::for($source)->query(Company::class)->findOrFail($company_id)
            : Company::query()->findOrFail($company_id);

        if ($source instanceof Model) {
            ConnectionScopedTransaction::connection($company, $source);
        }

        return ConnectionScopedTransaction::run($company, function (ConnectionScopedModels $models) use ($company_id, $item_id, $warehouse_id, $quantity_string, $source): StockMovement {
            $item = $this->resolveItem($models, $company_id, $item_id);
            $this->assertWarehouseBelongsToCompany($models, $company_id, $warehouse_id);

            $stock_level = $this->lockStockLevel($models, $company_id, $item_id, $warehouse_id);

            if ((float) $stock_level->quantity < (float) $quantity_string) {
                throw ValidationException::withMessages([
                    'quantity' => ['Insufficient stock for the requested quantity.'],
                ]);
            }

            $movement = $models->model(StockMovement::class)->fill([
                'company_id' => $company_id,
                'item_id' => $item_id,
                'warehouse_id' => $warehouse_id,
                'direction' => StockMovementDirection::Out,
                'quantity' => $quantity_string,
                'unit_cost' => null,
            ]);

            if ($source instanceof Model) {
                $movement->source()->associate($source);
            }

            if ($item->costing_method === 'fifo') {
                $movement_unit_cost = $this->consumeFifoLayersAndComputeUnitCost(
                    $models,
                    $company_id,
                    $item_id,
                    $warehouse_id,
                    $quantity_string,
                );
                $movement->unit_cost = $movement_unit_cost;
                $movement->save();

                $stock_level->quantity = $this->subtractDecimal($stock_level->quantity, $quantity_string);
                $this->syncFifoDisplayAverage($models, $stock_level);
                $stock_level->save();

                return $movement;
            }

            $avg = $stock_level->weighted_avg_cost;
            $movement->unit_cost = $avg;
            $movement->save();

            $new_qty = $this->subtractDecimal($stock_level->quantity, $quantity_string);
            $stock_level->quantity = $new_qty;

            if ((float) $new_qty === 0.0) {
                $stock_level->weighted_avg_cost = '0.0000';
            }

            $stock_level->save();

            return $movement;
        });
    }

    private function resolveItem(ConnectionScopedModels $models, int $company_id, int $item_id): Item
    {
        /** @var Item|null $item */
        $item = $models->query(Item::class)
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

    private function assertWarehouseBelongsToCompany(ConnectionScopedModels $models, int $company_id, int $warehouse_id): void
    {
        $exists = $models->query(Warehouse::class)
            ->whereKey($warehouse_id)
            ->where('company_id', $company_id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['Warehouse not found for this company.'],
            ]);
        }
    }

    private function lockStockLevel(ConnectionScopedModels $models, int $company_id, int $item_id, int $warehouse_id): StockLevel
    {
        /** @var StockLevel|null $row */
        $row = $models->query(StockLevel::class)
            ->where('company_id', $company_id)
            ->where('item_id', $item_id)
            ->where('warehouse_id', $warehouse_id)
            ->lockForUpdate()
            ->first();

        if ($row !== null) {
            return $row;
        }

        return $models->query(StockLevel::class)->create([
            'company_id' => $company_id,
            'item_id' => $item_id,
            'warehouse_id' => $warehouse_id,
            'quantity' => '0.0000',
            'weighted_avg_cost' => '0.0000',
        ]);
    }

    /**
     * Consumes open FIFO layers (oldest first) and returns the weighted unit
     * cost for the outbound quantity.
     *
     * @return numeric-string
     */
    private function consumeFifoLayersAndComputeUnitCost(
        ConnectionScopedModels $models,
        int $company_id,
        int $item_id,
        int $warehouse_id,
        string $quantity_out,
    ): string {
        $available = (string) $models->query(StockCostLayer::class)
            ->where('company_id', $company_id)
            ->where('item_id', $item_id)
            ->where('warehouse_id', $warehouse_id)
            ->where('qty_remaining', '>', 0)
            ->sum('qty_remaining');

        if ((float) $available < (float) $quantity_out) {
            throw ValidationException::withMessages([
                'quantity' => ['Insufficient stock for the requested quantity.'],
            ]);
        }

        $remaining_to_take = $quantity_out;
        $total_cost = '0.0000';

        $layers = $models->query(StockCostLayer::class)
            ->where('company_id', $company_id)
            ->where('item_id', $item_id)
            ->where('warehouse_id', $warehouse_id)
            ->where('qty_remaining', '>', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->lazy(100);

        foreach ($layers as $layer) {
            if ((float) $remaining_to_take === 0.0) {
                break;
            }

            $layer_qty = $layer->qty_remaining;

            if ((float) $layer_qty === 0.0) {
                continue;
            }

            $take = $this->minDecimal($layer_qty, $remaining_to_take);
            $layer_cost = $this->multiplyDecimal($take, $layer->unit_cost);
            $total_cost = $this->addDecimal($total_cost, $layer_cost);

            $new_remaining = $this->subtractDecimal($layer_qty, $take);
            $layer->qty_remaining = $new_remaining;
            $layer->save();

            $remaining_to_take = $this->subtractDecimal($remaining_to_take, $take);
        }

        return $this->divideDecimal($total_cost, $quantity_out);
    }

    private function syncFifoDisplayAverage(ConnectionScopedModels $models, StockLevel $stock_level): void
    {
        $aggregate = $models->query(StockCostLayer::class)
            ->where('company_id', $stock_level->company_id)
            ->where('item_id', $stock_level->item_id)
            ->where('warehouse_id', $stock_level->warehouse_id)
            ->where('qty_remaining', '>', 0)
            ->selectRaw('SUM(qty_remaining) as qty_sum, SUM(qty_remaining * unit_cost) as value_sum')
            ->first();

        $layer_qty_sum = (string) ($aggregate?->qty_sum ?? '0.0000');
        $value = (string) ($aggregate?->value_sum ?? '0.0000');

        if ((float) $layer_qty_sum === 0.0) {
            $stock_level->weighted_avg_cost = '0.0000';

            return;
        }

        $stock_level->weighted_avg_cost = $this->divideDecimal($value, $layer_qty_sum);
    }

    /**
     * @return numeric-string
     */
    private function normalizeQuantityString(string|float|int $value): string
    {
        return $this->formatMoney4((float) $value);
    }

    /**
     * @return numeric-string
     */
    private function normalizeMoneyString(string|float|int $value): string
    {
        $as_float = is_string($value) ? (float) $value : (float) $value;

        return $this->formatMoney4($as_float);
    }

    /**
     * @return numeric-string
     */
    private function addDecimal(string $a, string $b): string
    {
        return $this->formatMoney4((float) $a + (float) $b);
    }

    /**
     * @return numeric-string
     */
    private function subtractDecimal(string $a, string $b): string
    {
        return $this->formatMoney4((float) $a - (float) $b);
    }

    /**
     * @return numeric-string
     */
    private function minDecimal(string $a, string $b): string
    {
        return (float) $a <= (float) $b ? $this->formatMoney4((float) $a) : $this->formatMoney4((float) $b);
    }

    /**
     * @return numeric-string
     */
    private function multiplyDecimal(string $a, string $b): string
    {
        return $this->formatMoney4((float) $a * (float) $b);
    }

    /**
     * @return numeric-string
     */
    private function divideDecimal(string $a, string $b): string
    {
        $den = (float) $b;

        if (abs($den) < 0.0000001) {
            return '0.0000';
        }

        return $this->formatMoney4((float) $a / $den);
    }

    /**
     * @return numeric-string
     */
    private function formatMoney4(float $value): string
    {
        return number_format(round($value, 4), 4, '.', '');
    }
}
