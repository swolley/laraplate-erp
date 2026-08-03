<?php

declare(strict_types=1);

namespace Modules\ERP\Import\Enums;

enum ImportMutation: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Skipped = 'skipped';
}
