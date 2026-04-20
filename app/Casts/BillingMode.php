<?php

declare(strict_types=1);

namespace Modules\Business\Casts;

enum BillingMode: string
{
    case UNIT = 'unit';
    case FIXED = 'fixed';

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
