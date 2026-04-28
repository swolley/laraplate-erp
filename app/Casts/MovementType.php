<?php

declare(strict_types=1);

namespace Modules\Business\Casts;

/**
 * Synthetic cash direction for Tricount-style adapters mapped to journal lines.
 */
enum MovementType: string
{
    case Income = 'income';
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
