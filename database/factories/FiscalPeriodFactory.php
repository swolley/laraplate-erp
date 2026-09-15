<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;

/**
 * Standalone, this makes January of its year. Callers that need a full calendar ask the year for
 * it with `FiscalYearFactory::withPeriods()` rather than assembling periods by hand.
 *
 * @extends Factory<FiscalPeriod>
 */
final class FiscalPeriodFactory extends Factory
{
    /**
     * @var class-string<FiscalPeriod>
     */
    protected $model = FiscalPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (int) date('Y');

        return [
            'fiscal_year_id' => FiscalYear::factory(),
            'period_no' => 1,
            'start_date' => Carbon::create($year, 1, 1)->startOfDay(),
            'end_date' => Carbon::create($year, 1, 31)->endOfDay(),
            'is_closed' => false,
        ];
    }

    public function closed(): self
    {
        return $this->state(fn (array $attributes): array => ['is_closed' => true]);
    }
}
