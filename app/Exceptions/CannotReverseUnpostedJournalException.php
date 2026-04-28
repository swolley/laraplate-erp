<?php

declare(strict_types=1);

namespace Modules\Business\Exceptions;

use RuntimeException;

final class CannotReverseUnpostedJournalException extends RuntimeException
{
    public static function make(int $journal_entry_id): self
    {
        return new self('Cannot reverse journal entry ' . $journal_entry_id . ' because it is not posted.');
    }
}
