<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

enum BankStatementLineStatus: string
{
    case Imported = 'imported';
    case Matched = 'matched';
    case Ignored = 'ignored';

    /**
     * @return array<int, string>
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
