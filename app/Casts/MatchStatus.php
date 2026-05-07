<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

enum MatchStatus: string
{
    case Matched = 'matched';
    case Tolerance = 'tolerance';
    case Forced = 'forced';
    case Unmatched = 'unmatched';

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
