<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

enum BillingMode: string
{
    case Unit = 'unit';
    case Fixed = 'fixed';

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
