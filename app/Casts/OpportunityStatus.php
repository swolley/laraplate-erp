<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

enum OpportunityStatus: string
{
    case OPEN = 'open';
    case WON = 'won';
    case LOST = 'lost';
    case CANCELLED = 'cancelled';

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
