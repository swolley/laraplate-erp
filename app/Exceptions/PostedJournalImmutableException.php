<?php

declare(strict_types=1);

namespace Modules\ERP\Exceptions;

use RuntimeException;

/**
 * Thrown when a posted journal header or line is mutated or removed outside {@see \Modules\ERP\Services\Accounting\JournalPostingService}.
 */
final class PostedJournalImmutableException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Posted journal entries and their lines cannot be updated or deleted.');
    }
}
