<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;

/**
 * @extends Factory<Account>
 */
final class AccountFactory extends Factory
{
    /**
     * @var class-string<Account>
     */
    protected $model = Account::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => (string) $this->faker->unique()->numerify('######'),
            'name' => mb_ucfirst($this->faker->unique()->words(2, true)),
            'kind' => AccountKind::Asset,
            'parent_id' => null,
            'is_active' => true,
        ];
    }

    public function kind(AccountKind $kind): self
    {
        return $this->state(fn (array $attributes): array => ['kind' => $kind]);
    }
}
