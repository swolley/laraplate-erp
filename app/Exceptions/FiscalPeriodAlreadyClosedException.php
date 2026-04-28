<?php

declare(strict_types=1);

namespace Modules\Business\Exceptions;

use RuntimeException;

final class FiscalPeriodAlreadyClosedException extends RuntimeException
{
    public static function forPeriod(int $period_id): self
    {
        return new self(sprintf('Fiscal period %d is already closed.', $period_id));
    }
}
