<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

enum PaymentRunFormat: string
{
    case SepaPain001 = 'sepa_pain_001';

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
