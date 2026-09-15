<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ERP\Casts\DeliveryNoteDirection;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;

/**
 * Outbound by default, the customer-facing direction. `sales_order_id` stays null: a delivery note
 * raised from an order is created by the evasion service, not assembled by a factory.
 *
 * @extends Factory<DeliveryNote>
 */
final class DeliveryNoteFactory extends Factory
{
    /**
     * @var class-string<DeliveryNote>
     */
    protected $model = DeliveryNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'sales_order_id' => null,
            'direction' => DeliveryNoteDirection::Outbound->value,
            'reference' => null,
            'delivered_at' => now(),
        ];
    }

    public function inbound(): self
    {
        return $this->state(fn (array $attributes): array => ['direction' => DeliveryNoteDirection::Inbound->value]);
    }

    public function numbered(string $reference): self
    {
        return $this->state(fn (array $attributes): array => ['reference' => $reference]);
    }
}
