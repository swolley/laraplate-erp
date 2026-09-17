<?php

declare(strict_types=1);

namespace Modules\ERP\Models\Pivot;

use Modules\Core\Models\Pivot\Presettable as CorePresettable;
use Modules\ERP\Models\Entity;
use Modules\ERP\Models\Preset;
use Override;

/**
 * @mixin IdeHelperPresettable
 */
final class Presettable extends CorePresettable
{
    #[Override]
    protected function presetModelClass(): string
    {
        return Preset::class;
    }

    #[Override]
    protected function entityModelClass(): string
    {
        return Entity::class;
    }
}
