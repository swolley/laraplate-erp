<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Reporting;

use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\StockLevel;

/**
 * Generates stock valuation snapshots from current stock levels.
 */
final class StockValuationService
{
    /**
     * @param  array{warehouse_id?: int|null}  $filters
     * @return array{
     *     rows: array<int, array{
     *         item_id: int,
     *         item_name: string,
     *         sku: string,
     *         warehouse_id: int,
     *         warehouse_name: string,
     *         warehouse_code: string,
     *         quantity: string,
     *         weighted_avg_cost: string,
     *         value: string,
     *     }>,
     *     total_quantity: string,
     *     total_value: string,
     * }
     */
    public function generate(int $company_id, array $filters = []): array
    {
        $warehouse_id = $filters['warehouse_id'] ?? null;
        $stock_levels_table = ERPTables::StockLevels->value;
        $items_table = ERPTables::Items->value;
        $warehouses_table = ERPTables::Warehouses->value;

        $totals = StockLevel::query()
            ->where('company_id', $company_id)
            ->when($warehouse_id !== null && $warehouse_id > 0, static fn ($query) => $query->where('warehouse_id', $warehouse_id))
            ->selectRaw('COALESCE(SUM(quantity), 0) as total_quantity, COALESCE(SUM(quantity * weighted_avg_cost), 0) as total_value')
            ->first();

        $stock_levels = StockLevel::query()
            ->with(['item', 'warehouse'])
            ->join($items_table, "{$items_table}.id", '=', "{$stock_levels_table}.item_id")
            ->join($warehouses_table, "{$warehouses_table}.id", '=', "{$stock_levels_table}.warehouse_id")
            ->where("{$stock_levels_table}.company_id", $company_id)
            ->when($warehouse_id !== null && $warehouse_id > 0, static fn ($query) => $query->where("{$stock_levels_table}.warehouse_id", $warehouse_id))
            ->orderBy("{$items_table}.sku")
            ->orderBy("{$warehouses_table}.code")
            ->select("{$stock_levels_table}.*")
            ->get();

        $rows = [];

        foreach ($stock_levels as $stock_level) {
            $quantity = (float) $stock_level->quantity;
            $weighted_avg_cost = (float) $stock_level->weighted_avg_cost;
            $value = $quantity * $weighted_avg_cost;
            $item = $stock_level->item;
            $warehouse = $stock_level->warehouse;

            $rows[] = [
                'item_id' => $stock_level->item_id,
                'item_name' => $item === null ? '' : $item->name,
                'sku' => $item === null || $item->sku === null ? '' : $item->sku,
                'warehouse_id' => $stock_level->warehouse_id,
                'warehouse_name' => $warehouse === null ? '' : $warehouse->name,
                'warehouse_code' => $warehouse === null ? '' : $warehouse->code,
                'quantity' => $this->formatAmount($quantity),
                'weighted_avg_cost' => $this->formatAmount($weighted_avg_cost),
                'value' => $this->formatAmount($value),
            ];
        }

        return [
            'rows' => $rows,
            'total_quantity' => $this->formatAmount((float) ($totals?->total_quantity ?? 0)),
            'total_value' => $this->formatAmount((float) ($totals?->total_value ?? 0)),
        ];
    }

    private function formatAmount(float $amount): string
    {
        return number_format(round($amount, 4), 4, '.', '');
    }
}
