<?php

declare(strict_types=1);

namespace Modules\Business\Services\Accounting;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Company;
use Modules\Business\Models\FiscalPeriod;
use Modules\Business\Models\FiscalYear;

/**
 * Ensures a calendar-year fiscal cycle (Jan–Dec) with twelve monthly periods exists.
 */
final class FiscalCalendarInstaller
{
    /**
     * Create the fiscal year and twelve Gregorian months when missing.
     */
    public function ensureCalendarYear(Company $company, int $year): FiscalYear
    {
        $company_id = (int) $company->getKey();

        $existing = FiscalYear::query()->withoutGlobalScopes()
            ->where('company_id', $company_id)
            ->where('year', $year)
            ->first();

        if ($existing instanceof FiscalYear) {
            return $existing;
        }

        $start = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $end = CarbonImmutable::create($year, 12, 31)->endOfDay();

        return DB::transaction(function () use ($company_id, $year, $start, $end): FiscalYear {
            $fiscal_year = new FiscalYear([
                'company_id' => $company_id,
                'year' => $year,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'is_closed' => false,
            ]);
            $fiscal_year->setSkipValidation(true);
            $fiscal_year->save();

            for ($m = 1; $m <= 12; $m++) {
                $p_start = CarbonImmutable::create($year, $m, 1)->startOfDay();
                $p_end = $p_start->endOfMonth();

                $period = new FiscalPeriod([
                    'fiscal_year_id' => (int) $fiscal_year->getKey(),
                    'period_no' => $m,
                    'start_date' => $p_start->toDateString(),
                    'end_date' => $p_end->toDateString(),
                    'is_closed' => false,
                ]);
                $period->setSkipValidation(true);
                $period->save();
            }

            return $fiscal_year;
        });
    }
}
