<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Casts\EntityType;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Models\Taxonomy;
use Override;

/**
 * @mixin IdeHelperActivity
 */
class Activity extends Taxonomy
{
    #[Override]
    public static function getEntityModelClass(): string
    {
        return Entity::class;
    }

    public function time_entries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function price_list_items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    #[Override]
    protected static function getEntityType(): IDynamicEntityTypable
    {
        return EntityType::ACTIVITIES;
    }
}
