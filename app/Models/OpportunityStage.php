<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Modules\ERP\Casts\EntityType;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Models\Taxonomy;
use Override;

/**
 * CRM pipeline stage node (M3.1); rows live in `taxonomies` with {@see EntityType::OPPORTUNITY_STAGES}.
 *
 * @mixin IdeHelperOpportunityStage
 */
class OpportunityStage extends Taxonomy
{
    #[Override]
    public static function getEntityModelClass(): string
    {
        return Entity::class;
    }

    #[Override]
    protected static function getEntityType(): IDynamicEntityTypable
    {
        return EntityType::OPPORTUNITY_STAGES;
    }
}
