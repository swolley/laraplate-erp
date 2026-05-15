<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Partial = 'partial';
    case Received = 'received';

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
