<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Concerns\HasValidity;
use Modules\Core\Overrides\Model;
use Modules\ERP\Enums\ERPTables;
use Override;

// use Modules\ERP\Database\Factories\TaskFactory;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperTask
 */
final class Task extends Model
{
    use HasValidity;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::Tasks->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
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

    /**
     * @return array<string, mixed>
     */

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'project_id' => ['nullable', 'integer', 'exists:' . ERPTables::Projects->value . ',id'],
            'site_id' => ['nullable', 'integer', 'exists:' . ERPTables::Sites->value . ',id'],
            'taxonomy_id' => ['required', 'integer', 'exists:' . CoreTables::Taxonomies->value . ',id'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'project_id' => ['nullable', 'integer', 'exists:' . ERPTables::Projects->value . ',id'],
            'site_id' => ['nullable', 'integer', 'exists:' . ERPTables::Sites->value . ',id'],
            'taxonomy_id' => ['sometimes', 'integer', 'exists:' . CoreTables::Taxonomies->value . ',id'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'valid_from' => 'immutable_datetime',
            'valid_to' => 'immutable_datetime',
        ];
    }
}
