<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Reporting;

use Modules\ERP\Models\StockLevel;

/**
 * Generates stock valuation snapshots from current stock levels.
 */
final class StockValuationService
{
    /**
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
    public function generate(int $company_id): array
    {
        $stock_levels = StockLevel::query()
            ->with(['item', 'warehouse'])
            ->where('company_id', $company_id)
            ->get()
            ->sortBy([
                static fn (StockLevel $left, StockLevel $right): int => strcmp(self::itemSku($left), self::itemSku($right)),
                static fn (StockLevel $left, StockLevel $right): int => strcmp(self::warehouseCode($left), self::warehouseCode($right)),
            ])
            ->values();

        $rows = [];
        $total_quantity = 0.0;
        $total_value = 0.0;

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

            $total_quantity += $quantity;
            $total_value += $value;
        }

        return [
            'rows' => $rows,
            'total_quantity' => $this->formatAmount($total_quantity),
            'total_value' => $this->formatAmount($total_value),
        ];
    }

    private function formatAmount(float $amount): string
    {
        return number_format(round($amount, 4), 4, '.', '');
    }

    private static function itemSku(StockLevel $stock_level): string
    {
        $item = $stock_level->item;

        if ($item === null || $item->sku === null) {
            return '';
        }

        return $item->sku;
    }

    private static function warehouseCode(StockLevel $stock_level): string
    {
        $warehouse = $stock_level->warehouse;

        return $warehouse === null ? '' : $warehouse->code;
    }
}
