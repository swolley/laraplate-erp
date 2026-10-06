<?php

declare(strict_types=1);

namespace Modules\ERP\Exceptions;

use RuntimeException;

/**
 * Thrown when a reservation asks for more than the item's available quantity
 * (on hand minus live reservations).
 */
final class InsufficientStockException extends RuntimeException
{
    public static function forReservation(int $company_id, int $item_id, string $requested, string $available): self
    {
        return new self(sprintf(
            'Cannot reserve %s of item %d for company %d: only %s available.',
            $requested,
            $item_id,
            $company_id,
            $available,
        ));
    }
}
