<?php

declare(strict_types=1);

namespace Modules\Business\Exceptions;

use RuntimeException;

/**
 * Thrown when a line references a GL account that does not belong to the entry company.
 */
final class JournalAccountNotInCompanyException extends RuntimeException
{
    public static function forAccount(int $account_id, int $company_id): self
    {
        return new self(
            \sprintf(
                'Account id %d is not part of company id %d',
                $account_id,
                $company_id,
            ),
        );
    }
}
