<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ERP\Casts\TracingType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;

/**
 * `sku` is unique per company, so it is generated from the factory's own unique sequence rather
 * than from a word list that collides once a test creates more than a handful of items.
 *
 * @extends Factory<Item>
 */
final class ItemFactory extends Factory
{
    /**
     * @var class-string<Item>
     */
    protected $model = Item::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => mb_ucfirst($this->faker->unique()->words(2, true)),
            'sku' => 'SKU-' . mb_strtoupper($this->faker->unique()->bothify('??####')),
            'uom' => 'unit',
            'costing_method' => 'fifo',
            'tracing_type' => TracingType::None->value,
        ];
    }

    /**
     * Weighted-average costing, the alternative the schema allows.
     */
    public function weightedAverage(): self
    {
        return $this->state(fn (array $attributes): array => ['costing_method' => 'weighted_avg']);
    }

    public function lotTraced(): self
    {
        return $this->state(fn (array $attributes): array => ['tracing_type' => TracingType::Lot->value]);
    }
}
