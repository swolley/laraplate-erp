<?php

declare(strict_types=1);

namespace Modules\ERP\Models\Pivot;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\ERP\Models\Entity;
use Modules\ERP\Models\Preset;
use Modules\Core\Models\Pivot\Presettable as CorePresettable;
use Override;

/**
 * @property int $version
 * @property array<int, array{field_id: int, name: string, type: string, options: mixed, is_translatable: bool, is_slug: bool, pivot: array{is_required: bool, order_column: int, default: mixed}}> $fields_snapshot
 * @mixin IdeHelperPresettable
 */
final class Presettable extends CorePresettable
{
    /**
     * @return BelongsTo<Preset>
     */
    #[Override]
    public function preset(): BelongsTo
    {
        return $this->belongsTo(Preset::class);
    }

    /**
     * @return BelongsTo<Entity>
     */
    #[Override]
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
