<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;

/**
 * @extends Factory<FiscalYear>
 */
final class FiscalYearFactory extends Factory
{
    /**
     * @var class-string<FiscalYear>
     */
    protected $model = FiscalYear::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (int) date('Y');

        return [
            'company_id' => Company::factory(),
            'year' => $year,
            'start_date' => Carbon::create($year, 1, 1)->startOfDay(),
            'end_date' => Carbon::create($year, 12, 31)->endOfDay(),
            'is_closed' => false,
        ];
    }

    /**
     * Relations in this module are snake_case (`fiscal_year`), which Laravel's `for()` cannot
     * guess from the model name, so the relation is always named explicitly.
     *
     * A calendar no document can fall through: the periods tile the year exactly, the last one
     * absorbing the remainder when the count does not divide twelve.
     */
    public function withPeriods(int $count = 12): self
    {
        return $this->afterCreating(function (FiscalYear $fiscal_year) use ($count): void {
            $start = $fiscal_year->start_date->copy()->startOfDay();
            $end = $fiscal_year->end_date->copy()->endOfDay();
            $months = max(1, (int) round($start->diffInMonths($end) + 1));
            $step = max(1, (int) floor($months / $count));

            $cursor = $start->copy();

            for ($period_no = 1; $period_no <= $count; $period_no++) {
                $period_end = $period_no === $count
                    ? $end->copy()
                    : $cursor->copy()->addMonths($step)->subDay()->endOfDay();

                FiscalPeriod::factory()->for($fiscal_year, 'fiscal_year')->create([
                    'period_no' => $period_no,
                    'start_date' => $cursor->copy(),
                    'end_date' => $period_end,
                ]);

                $cursor = $period_end->copy()->addDay()->startOfDay();
            }
        });
    }

    public function closed(): self
    {
        return $this->state(fn (array $attributes): array => ['is_closed' => true]);
    }
}
