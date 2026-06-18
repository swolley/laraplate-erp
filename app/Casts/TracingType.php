<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

/**
 * Traceability level for an item.
 *
 * - none: no traceability required
 * - lot: item is tracked by lot number
 * - serial: item is tracked by individual serial number
 */
enum TracingType: string
{
    case None = 'none';
    case Lot = 'lot';
    case Serial = 'serial';

    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
