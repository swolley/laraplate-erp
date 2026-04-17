<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;

// use Modules\Business\Database\Factories\TaskFactory;

/**
 * @mixin IdeHelperTask
 */
class Task extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): TaskFactory
    // {
    //     // return TaskFactory::new();
    // }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
