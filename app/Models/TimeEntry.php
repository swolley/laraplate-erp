<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\ERP\Observers\TimeEntryObserver;
use Modules\ERP\Rules\TimeEntryOverlap;
use Modules\Core\Overrides\Model;
use Override;

#[ObservedBy([TimeEntryObserver::class])]
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

    /**
     * Whether any other (non-soft-deleted) TimeEntry of the given user
     * overlaps the half-open interval [startedAt, endedAt).
     *
     * `endedAt = null` means an open-ended session (treated as future infinity).
     */
    public static function existsOverlapFor(
        int $userId,
        DateTimeInterface|string $startedAt,
        DateTimeInterface|string|null $endedAt,
        ?int $excludeId = null,
    ): bool {
        $query = static::query()
            ->forUser($userId)
            ->overlapping($startedAt, $endedAt);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Restrict the query to entries belonging to the given user.
     */
    #[Scope]
    protected function forUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Restrict the query to entries that overlap the given half-open
     * interval [startedAt, endedAt). `endedAt = null` is treated as
     * future infinity (open session).
     */
    #[Scope]
    protected function overlapping(
        Builder $query,
        DateTimeInterface|string $startedAt,
        DateTimeInterface|string|null $endedAt,
    ): Builder {
        $start = $startedAt instanceof DateTimeInterface ? $startedAt : Carbon::parse($startedAt);
        $end = $endedAt === null
            ? null
            : ($endedAt instanceof DateTimeInterface ? $endedAt : Carbon::parse($endedAt));

        if ($end !== null) {
            $query->where('started_at', '<', $end);
        }

        return $query->where(function (Builder $inner) use ($start): void {
            $inner->whereNull('ended_at')
                ->orWhere('ended_at', '>', $start);
        });
    }

    /**
     * Restrict the query to entries with the given taxonomy (activity) node.
     * Useful for time aggregations grouped by activity.
     */
    #[Scope]
    protected function forTaxonomy(Builder $query, int $taxonomyId): Builder
    {
        return $query->where('taxonomy_id', $taxonomyId);
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
            'ended_at' => ['nullable', 'date', 'after:started_at', new TimeEntryOverlap()],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'taxonomy_id' => ['sometimes', 'integer', 'exists:taxonomies,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'quotation_item_id' => ['nullable', 'integer', 'exists:quotations_items,id'],
            'started_at' => ['sometimes', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at', new TimeEntryOverlap($this->getKey())],
        ]);

        return $rules;
    }
}
