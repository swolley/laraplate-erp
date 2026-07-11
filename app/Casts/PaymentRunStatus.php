<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

enum PaymentRunStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Exported = 'exported';
    case Cancelled = 'cancelled';

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
