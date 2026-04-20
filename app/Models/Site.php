<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Place;
use Modules\Core\Overrides\Model;

/**
 * Physical premise (branch) for on-site work; optional LOCATION for calendar exports (ICS).
 *
 * @mixin IdeHelperSite
 */
class Site extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'place_id',
    ];

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return BelongsTo<Place, $this>
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
