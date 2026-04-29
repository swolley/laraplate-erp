<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Illuminate\Support\Facades\DB;
use Modules\ERP\Exceptions\FiscalPeriodAlreadyClosedException;
use Modules\ERP\Exceptions\FiscalYearAlreadyClosedException;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;

/**
 * Closes fiscal periods and whole fiscal years (administrative lock).
 *
 * Journal posting rules will consult `is_closed` in later milestones (M1 journal).
 */
final class FiscalPeriodCloser
{
    public function closePeriod(FiscalPeriod $period): void
    {
        if ($period->is_closed) {
            throw FiscalPeriodAlreadyClosedException::forPeriod((int) $period->getKey());
        }

        DB::transaction(function () use ($period): void {
            $period->is_closed = true;
            $period->setSkipValidation(true);
            $period->save();
        });
    }

    public function closeYear(FiscalYear $year): void
    {
        if ($year->is_closed) {
            throw FiscalYearAlreadyClosedException::forYear((int) $year->getKey());
        }

        DB::transaction(function () use ($year): void {
            $year->loadMissing('fiscal_periods');

            foreach ($year->fiscal_periods as $period) {
                if (! $period->is_closed) {
                    $period->is_closed = true;
                    $period->setSkipValidation(true);
                    $period->save();
                }
            }

            $year->is_closed = true;
            $year->setSkipValidation(true);
            $year->save();
        });
    }
}
