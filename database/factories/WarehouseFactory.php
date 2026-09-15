<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Warehouse;

/**
 * @extends Factory<Warehouse>
 */
final class WarehouseFactory extends Factory
{
    /**
     * @var class-string<Warehouse>
     */
    protected $model = Warehouse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Warehouse ' . mb_strtoupper($this->faker->unique()->lexify('???')),
            'code' => mb_strtoupper($this->faker->unique()->bothify('WH##')),
            'site_id' => null,
        ];
    }
}
