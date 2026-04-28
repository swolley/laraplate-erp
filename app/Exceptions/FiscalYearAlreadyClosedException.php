<?php

declare(strict_types=1);

namespace Modules\Business\Exceptions;

use RuntimeException;

final class FiscalYearAlreadyClosedException extends RuntimeException
{
    public static function forYear(int $fiscal_year_id): self
    {
        return new self(sprintf('Fiscal year %d is already closed.', $fiscal_year_id));
    }
}
