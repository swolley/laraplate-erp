<?php

declare(strict_types=1);

namespace Modules\Business\Casts;

/**
 * High-level account classes in double-entry bookkeeping.
 *
 * Naming is English in code; UI can map to Italian labels (e.g. "ATTIVITÀ").
 */
enum AccountKind: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}
