<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Models\Taxonomy;
use Modules\ERP\Casts\EntityType;
use Modules\ERP\Models\Pivot\Presettable;
use Override;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperActivity
 */
final class Activity extends Taxonomy
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

    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function time_entries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'taxonomy_id');
    }

    /**
     * @return HasMany<PriceListItem, $this>
     */
    public function price_list_items(): HasMany
    {
        return $this->hasMany(PriceListItem::class, 'taxonomy_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'taxonomy_id');
    }

    #[Override]
    protected static function getEntityType(): IDynamicEntityTypable
    {
        return EntityType::Activities;
    }
}
