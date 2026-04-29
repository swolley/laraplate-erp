<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

/**
 * Fiscal classification for rows in {@see \Modules\ERP\Models\TaxCode}.
 */
enum TaxKind: string
{
    case Vat = 'vat';
    case Withholding = 'withholding';

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
