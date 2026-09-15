<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ERP\Models\Company;

/**
 * The tenant root of the ERP domain.
 *
 * Every company-scoped factory hangs off this one, so the definition stays minimal and valid
 * rather than rich: a caller who needs a specific fiscal shape overrides it explicitly.
 *
 * @extends Factory<Company>
 */
final class CompanyFactory extends Factory
{
    /**
     * @var class-string<Company>
     */
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'slug' => $this->faker->unique()->slug(3),
            'name' => $name,
            'legal_name' => $name . ' S.r.l.',
            'tax_id' => (string) $this->faker->numerify('###########'),
            'fiscal_country' => 'IT',
            'default_currency' => 'EUR',
            'is_default' => false,
        ];
    }

    /**
     * The default tenant. At most one company per environment may hold this flag, so a caller
     * asks for it deliberately instead of receiving it by accident.
     */
    public function default(): self
    {
        return $this->state(fn (array $attributes): array => ['is_default' => true]);
    }
}
