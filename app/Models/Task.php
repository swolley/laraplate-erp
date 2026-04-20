<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Overrides\Model;
use Override;

// use Modules\Business\Database\Factories\TaskFactory;

/**
 * @mixin IdeHelperTask
 */
class Task extends Model
{
    use HasValidity;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_id',
        'site_id',
        'taxonomy_id',
    ];

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

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'taxonomy_id');
    }

    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function time_entries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'taxonomy_id' => ['required', 'integer', 'exists:taxonomies,id'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'taxonomy_id' => ['sometimes', 'integer', 'exists:taxonomies,id'],
        ]);

        return $rules;
    }
}
