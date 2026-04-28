<?php

declare(strict_types=1);

namespace Modules\Business\Exceptions;

use RuntimeException;

final class JournalEntryCompanyMismatchException extends RuntimeException
{
    public static function make(int $journal_entry_id, int $company_id): self
    {
        return new self(
            'Journal entry ' . $journal_entry_id . ' does not belong to company ' . $company_id,
        );
    }
}
