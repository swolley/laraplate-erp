<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Override;

/**
 * @mixin IdeHelperTimeEntry
 */
class TimeEntry extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'taxonomy_id',
        'task_id',
        'project_id',
        'quotation_item_id',
        'started_at',
        'ended_at',
    ];

    /**
     * @return BelongsTo<\Modules\Core\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(user_class());
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'taxonomy_id');
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<QuotationItem, $this>
     */
    public function quotation_item(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class, 'quotation_item_id');
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'taxonomy_id' => ['required', 'integer', 'exists:taxonomies,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'quotation_item_id' => ['nullable', 'integer', 'exists:quotations_items,id'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'taxonomy_id' => ['sometimes', 'integer', 'exists:taxonomies,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'quotation_item_id' => ['nullable', 'integer', 'exists:quotations_items,id'],
            'started_at' => ['sometimes', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
        ]);

        return $rules;
    }
}
