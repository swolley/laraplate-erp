<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ERP\Casts\PurchaseOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\PurchaseOrderLine;

/**
 * @extends Factory<PurchaseOrder>
 */
final class PurchaseOrderFactory extends Factory
{
    /**
     * @var class-string<PurchaseOrder>
     */
    protected $model = PurchaseOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            // Resolved after `company_id`: the model refuses a party from another company.
            'party_id' => fn (array $attributes): int => Party::factory()
                ->supplier()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'reference' => null,
            'currency' => 'EUR',
            'status' => PurchaseOrderStatus::Draft->value,
            'ordered_at' => now(),
        ];
    }

    public function withLines(int $count = 2): self
    {
        return $this->afterCreating(function (PurchaseOrder $order) use ($count): void {
            PurchaseOrderLine::factory()
                ->count($count)
                ->for($order, 'purchase_order')
                ->create();
        });
    }

    public function confirmed(): self
    {
        return $this->state(fn (array $attributes): array => ['status' => PurchaseOrderStatus::Confirmed->value]);
    }

    public function numbered(string $reference): self
    {
        return $this->state(fn (array $attributes): array => ['reference' => $reference]);
    }
}
