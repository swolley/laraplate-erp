<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Models\Taxonomy;
use Modules\ERP\Casts\EntityType;
use Modules\ERP\Models\Pivot\Presettable;
use Override;

/**
 * CRM pipeline stage node (M3.1); rows live in `taxonomies` with {@see EntityType::OpportunityStages}.
 *
 * @mixin \Eloquent
 * @mixin IdeHelperOpportunityStage
 */
final class OpportunityStage extends Taxonomy
{
    #[Override]
    public static function getEntityModelClass(): string
    {
        return Entity::class;
    }

    #[Override]
    public static function getPresettableClass(): string
    {
        return Presettable::class;
    }

    #[Override]
    protected static function getEntityType(): IDynamicEntityTypable
    {
        return EntityType::OpportunityStages;
    }
}
