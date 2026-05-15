<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

enum ProjectStatus: string
{
    case Active = 'active'; // work in progress
    case OnHold = 'on_hold'; // waiting for something
    case Completed = 'completed'; // project is completed, no more activities and waiting for payment
    case Cancelled = 'cancelled'; // project is cancelled, no more activities and not completed
    case Archived = 'archived'; // project is closed, no more activities and paid

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
