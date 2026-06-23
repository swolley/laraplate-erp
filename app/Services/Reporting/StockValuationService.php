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
                static fn (StockLevel $left, StockLevel $right): int => strcmp((string) $left->item?->sku, (string) $right->item?->sku),
                static fn (StockLevel $left, StockLevel $right): int => strcmp((string) $left->warehouse?->code, (string) $right->warehouse?->code),
            ])
            ->values();

        $rows = [];
        $total_quantity = 0.0;
        $total_value = 0.0;

        foreach ($stock_levels as $stock_level) {
            $quantity = (float) $stock_level->quantity;
            $weighted_avg_cost = (float) $stock_level->weighted_avg_cost;
            $value = $quantity * $weighted_avg_cost;

            $rows[] = [
                'item_id' => (int) $stock_level->item_id,
                'item_name' => (string) $stock_level->item?->name,
                'sku' => (string) $stock_level->item?->sku,
                'warehouse_id' => (int) $stock_level->warehouse_id,
                'warehouse_name' => (string) $stock_level->warehouse?->name,
                'warehouse_code' => (string) $stock_level->warehouse?->code,
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
}
