<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Carbon\CarbonImmutable;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Support\ConnectionScopedModels;
use Modules\ERP\Support\ConnectionScopedTransaction;

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
        $company_id = is_int($company->id) ? $company->id : (int) $company->id;

        $models = ConnectionScopedModels::for($company);
        $existing = $models->query(FiscalYear::class)->withoutGlobalScopes()
            ->where('company_id', $company_id)
            ->where('year', $year)
            ->first();

        if ($existing instanceof FiscalYear) {
            return $existing;
        }

        $start = CarbonImmutable::createFromDate($year, 1, 1)->startOfDay();
        $end = CarbonImmutable::createFromDate($year, 12, 31)->endOfDay();

        return ConnectionScopedTransaction::run($company, function (ConnectionScopedModels $models) use ($company_id, $year, $start, $end): FiscalYear {
            $fiscal_year = $models->model(FiscalYear::class)->fill([
                'company_id' => $company_id,
                'year' => $year,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'is_closed' => false,
            ]);
            $fiscal_year->setSkipValidation(true);
            $fiscal_year->save();

            for ($m = 1; $m <= 12; $m++) {
                $p_start = CarbonImmutable::createFromDate($year, $m, 1)->startOfDay();
                $p_end = $p_start->endOfMonth();

                $period = $models->model(FiscalPeriod::class)->fill([
                    'fiscal_year_id' => is_int($fiscal_year->id) ? $fiscal_year->id : (int) $fiscal_year->id,
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
