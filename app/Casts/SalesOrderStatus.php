<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

enum SalesOrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case PartiallyEvased = 'partially_evased';
    case FullyEvased = 'fully_evased';
    case Cancelled = 'cancelled';
    case Amended = 'amended';

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
