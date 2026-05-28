<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

enum SalesOrderLineStatus: string
{
    case Open = 'open';
    case PartiallyEvased = 'partially_evased';
    case FullyEvased = 'fully_evased';
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
