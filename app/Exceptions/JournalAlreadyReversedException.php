<?php

declare(strict_types=1);

namespace Modules\ERP\Exceptions;

use RuntimeException;

final class JournalAlreadyReversedException extends RuntimeException
{
    public static function make(int $journal_entry_id): self
    {
        return new self(
            'Journal entry ' . $journal_entry_id . ' already has a reversal voucher.',
        );
    }
}
