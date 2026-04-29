<?php

declare(strict_types=1);

namespace Modules\ERP\Exceptions;

use RuntimeException;

/**
 * Thrown when a journal entry is posted into a fiscal period that is already closed.
 */
final class PostingToClosedFiscalPeriodException extends RuntimeException
{
    public static function forPeriod(int $period_id): self
    {
        return new self('Cannot post to closed fiscal period id ' . $period_id);
    }
}
