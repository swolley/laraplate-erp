<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'address_line_1',
        'address_line_2',
        'city',
        'region',
        'postal_code',
        'country_code',
        'latitude',
        'longitude',
    ];

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
