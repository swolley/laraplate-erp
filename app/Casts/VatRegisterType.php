<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

enum VatRegisterType: string
{
    case Sales = 'sales';
    case Purchases = 'purchases';

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
