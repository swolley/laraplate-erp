<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\ERP\Casts\EntityType;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Models\Taxonomy;
use Modules\ERP\Models\Pivot\Presettable;
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
        return $this->hasMany(TimeEntry::class, 'taxonomy_id');
    }

    public function price_list_items(): HasMany
    {
        return $this->hasMany(PriceListItem::class, 'taxonomy_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'taxonomy_id');
    }

    #[Override]
    protected static function getEntityType(): IDynamicEntityTypable
    {
        return EntityType::ACTIVITIES;
    }

    #[Override]
    public static function getPresettableClass(): string
    {
        return Presettable::class;
    }
}
