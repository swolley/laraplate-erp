<?php

declare(strict_types=1);

namespace Modules\Business\Casts;

enum BillingMode: string
{
    case UNIT = 'unit';
    case FIXED = 'fixed';
}
