<?php

declare(strict_types=1);

namespace Modules\ERP\Enums;

/**
 * Lifecycle of a stock reservation. `Soft` and `Hard` are the live states that hold quantity
 * back from availability; `Consumed` and `Released` are terminal.
 */
enum StockReservationState: string
{
    case Soft = 'soft';
    case Hard = 'hard';
    case Consumed = 'consumed';
    case Released = 'released';

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
