<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;

/**
 * `reference` stays null: `DocumentNumberAllocator` owns it, and a factory that filled it in
 * would hide the numbering behaviour the tests exist to verify.
 *
 * @extends Factory<SalesOrder>
 */
final class SalesOrderFactory extends Factory
{
    /**
     * @var class-string<SalesOrder>
     */
    protected $model = SalesOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            // Resolved after `company_id`, so the customer lands in the same company the caller
            // chose. Handing the same Company factory to both fields would create two.
            'party_id' => fn (array $attributes): int => Party::factory()
                ->customer()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'reference' => null,
            'currency' => 'EUR',
            'status' => SalesOrderStatus::Draft->value,
        ];
    }

    public function withLines(int $count = 2): self
    {
        return $this->afterCreating(function (SalesOrder $order) use ($count): void {
            SalesOrderLine::factory()
                ->count($count)
                ->for($order, 'sales_order')
                ->create();
        });
    }

    public function confirmed(): self
    {
        return $this->state(fn (array $attributes): array => ['status' => SalesOrderStatus::Confirmed->value]);
    }

    public function numbered(string $reference): self
    {
        return $this->state(fn (array $attributes): array => ['reference' => $reference]);
    }
}
