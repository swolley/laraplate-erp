<?php

declare(strict_types=1);

namespace Modules\Business\Casts;

enum ProjectStatus: string
{
    case ACTIVE = 'active'; // work in progress
    case ON_HOLD = 'on_hold'; // waiting for something
    case COMPLETED = 'completed'; // project is completed, no more activities and waiting for payment
    case CANCELLED = 'cancelled'; // project is cancelled, no more activities and not completed
    case ARCHIVED = 'archived'; // project is closed, no more activities and paid
}
