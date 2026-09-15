<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;

/**
 * @extends Factory<SalesOrderLine>
 */
final class SalesOrderLineFactory extends Factory
{
    /**
     * @var class-string<SalesOrderLine>
     */
    protected $model = SalesOrderLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sales_order_id' => SalesOrder::factory(),
            // The header's company owns the line's item: the model refuses an item from
            // another company, and resolving it from the header is the only way a line
            // created standalone still lands in a consistent document.
            'item_id' => fn (array $attributes): int => Item::factory()->create([
                'company_id' => SalesOrder::query()->whereKey($attributes['sales_order_id'])->value('company_id'),
            ])->id,
            'name' => mb_ucfirst($this->faker->words(2, true)),
            'qty_ordered' => $this->faker->numberBetween(1, 20),
            'qty_delivered' => 0,
            'qty_invoiced' => 0,
            'qty_returned' => 0,
            'unit_price' => $this->faker->randomFloat(2, 5, 500),
            'status' => SalesOrderLineStatus::Open->value,
        ];
    }
}
