<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

enum OpportunityStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';
    case Cancelled = 'cancelled';

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
