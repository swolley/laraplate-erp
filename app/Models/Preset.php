<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\ERP\Casts\EntityType;
use Modules\ERP\Models\Pivot\Presettable;
use Modules\Core\Models\Preset as CorePreset;
use Override;

/**
 * Business preset model; behaviour lives in Core — this class exists for the Business namespace and Filament resources.
 *
 * @mixin IdeHelperPreset
 */
final class Preset extends CorePreset
{
    /**
     * @return HasManyThrough<Activity>
     */
    public function activities(): HasManyThrough
    {
        return $this->hasManyThrough(
            $this->getRelatedModelClass(),
            Presettable::class,
            'preset_id',      // foreign key on presettables pointing to presets
            'presettable_id', // foreign key on activities pointing to presettables
        );
    }

    /**
     * Migrate all activities to the latest presettable version.
     * This reassigns every activity's presettable_id to the current active version.
     */
    public function migrateActivitiesToLastVersion(): void
    {
        $this->migrateRelatedModelsToLastVersion();
    }

    #[Override]
    protected function newBaseQueryBuilder(): Builder
    {
        return parent::newBaseQueryBuilder()->whereExists(function (Builder $query): void {
            $query->select(DB::raw('1'))
                ->from('entities')
                ->whereIn('entities.type', EntityType::values());
        });
    }

    protected static function getRelatedModelClass(): string
    {
        return Activity::class;
    }
}
