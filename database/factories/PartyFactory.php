<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Party;

/**
 * A party is a customer, a supplier, or both. The definition picks customer because that is the
 * column default; the two states make the intent explicit where a test depends on it.
 *
 * @extends Factory<Party>
 */
final class PartyFactory extends Factory
{
    /**
     * @var class-string<Party>
     */
    protected $model = Party::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->unique()->company(),
            'tax_id' => (string) $this->faker->numerify('###########'),
            'vat_number' => 'IT' . $this->faker->numerify('###########'),
            'fiscal_country' => 'IT',
            'address_line' => $this->faker->streetAddress(),
            'postal_code' => (string) $this->faker->numerify('#####'),
            'city' => $this->faker->city(),
            'province' => mb_strtoupper($this->faker->lexify('??')),
            'country' => 'IT',
            'is_customer' => true,
            'is_supplier' => false,
        ];
    }

    public function customer(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_customer' => true,
            'is_supplier' => false,
        ]);
    }

    public function supplier(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_customer' => false,
            'is_supplier' => true,
        ]);
    }
}
