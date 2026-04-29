<?php

declare(strict_types=1);

namespace Modules\ERP\Exceptions;

use RuntimeException;

/**
 * Thrown when a fiscal period does not belong to the company used for posting.
 */
final class FiscalPeriodCompanyMismatchException extends RuntimeException
{
    public static function make(int $fiscal_period_id, int $company_id): self
    {
        return new self(
            \sprintf(
                'Fiscal period %d does not belong to company %d',
                $fiscal_period_id,
                $company_id,
            ),
        );
    }
}
