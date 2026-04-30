<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

enum SalesOrderStatus: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case PARTIALLY_EVASED = 'partially_evased';
    case FULLY_EVASED = 'fully_evased';
    case CANCELLED = 'cancelled';
    case AMENDED = 'amended';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function validationRule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
