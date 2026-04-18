<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Modules\Business\Casts\EntityType;
use Modules\Core\Models\Entity as CoreEntity;
use Modules\Core\Models\Pivot\Presettable;
use Override;

/**
 * @mixin IdeHelperEntity
 */
final class Entity extends CoreEntity
{
    /**
     * The activities that belong to the entity.
     *
     * @return HasManyThrough<Activity>
     */
    public function activities(): HasManyThrough
    {
        return $this->hasManyThrough(
            Activity::class,
            Presettable::class,
            'entity_id',      // foreign key on presettables pointing to entities
            'presettable_id', // foreign key on activities pointing to presettables
        );
    }

    // /**
    //  * The categories that belong to the entity.
    //  *
    //  * @return HasManyThrough<Category>
    //  */
    // public function categories(): HasManyThrough
    // {
    //     return $this->hasManyThrough(
    //         Category::class,
    //         Presettable::class,
    //         'entity_id',      // foreign key on presettables pointing to entities
    //         'presettable_id', // foreign key on categories pointing to presettables
    //     );
    // }

    #[Override]
    protected static function getEntityTypeEnumClass(): string
    {
        return EntityType::class;
    }
}
