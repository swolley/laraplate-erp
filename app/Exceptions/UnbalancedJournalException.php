<?php

declare(strict_types=1);

namespace Modules\Business\Exceptions;

use RuntimeException;

/**
 * Thrown when journal line amounts in functional currency do not net to zero.
 */
final class UnbalancedJournalException extends RuntimeException
{
    public static function forNonZeroSum(string $remainder): self
    {
        return new self(
            'Journal entry lines must balance on amount_local; remainder is ' . $remainder,
        );
    }
}
