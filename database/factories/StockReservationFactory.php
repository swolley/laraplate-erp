<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ERP\Enums\StockReservationState;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\StockReservation;

/**
 * A live soft reservation with no warehouse. The item belongs to the reservation's own company so
 * a standalone reservation is already internally consistent; `source_type` is an opaque label.
 *
 * @extends Factory<StockReservation>
 */
final class StockReservationFactory extends Factory
{
    /**
     * @var class-string<StockReservation>
     */
    protected $model = StockReservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'item_id' => fn (array $attributes): int => Item::factory()->create([
                'company_id' => $attributes['company_id'],
            ])->id,
            'warehouse_id' => null,
            'source_type' => 'external_document',
            'source_id' => $this->faker->numberBetween(1, 1_000_000),
            'quantity' => $this->faker->numberBetween(1, 20),
            'state' => StockReservationState::Soft->value,
            'expires_at' => null,
        ];
    }

    public function soft(): self
    {
        return $this->state(fn (array $attributes): array => ['state' => StockReservationState::Soft->value]);
    }

    public function hard(): self
    {
        return $this->state(fn (array $attributes): array => ['state' => StockReservationState::Hard->value]);
    }

    public function consumed(): self
    {
        return $this->state(fn (array $attributes): array => ['state' => StockReservationState::Consumed->value]);
    }

    public function released(): self
    {
        return $this->state(fn (array $attributes): array => ['state' => StockReservationState::Released->value]);
    }
}
