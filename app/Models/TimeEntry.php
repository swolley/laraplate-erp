<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Overrides\Model;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Observers\TimeEntryObserver;
use Modules\ERP\Rules\TimeEntryOverlap;
use Override;

#[ObservedBy([TimeEntryObserver::class])]
/**
 * @mixin \Eloquent
 * @mixin IdeHelperTimeEntry
 */
final class TimeEntry extends Model
{
    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::TimeEntries->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
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
        $query = self::query()
            ->forUser($userId)
            ->overlapping($startedAt, $endedAt);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

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

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'user_id' => ['required', 'integer', 'exists:' . CoreTables::Users->value . ',id'],
            'taxonomy_id' => ['required', 'integer', 'exists:' . CoreTables::Taxonomies->value . ',id'],
            'task_id' => ['nullable', 'integer', 'exists:' . ERPTables::Tasks->value . ',id'],
            'project_id' => ['nullable', 'integer', 'exists:' . ERPTables::Projects->value . ',id'],
            'quotation_item_id' => ['nullable', 'integer', 'exists:' . ERPTables::QuotationItems->value . ',id'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at', new TimeEntryOverlap()],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'user_id' => ['sometimes', 'integer', 'exists:' . CoreTables::Users->value . ',id'],
            'taxonomy_id' => ['sometimes', 'integer', 'exists:' . CoreTables::Taxonomies->value . ',id'],
            'task_id' => ['nullable', 'integer', 'exists:' . ERPTables::Tasks->value . ',id'],
            'project_id' => ['nullable', 'integer', 'exists:' . ERPTables::Projects->value . ',id'],
            'quotation_item_id' => ['nullable', 'integer', 'exists:' . ERPTables::QuotationItems->value . ',id'],
            'started_at' => ['sometimes', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at', new TimeEntryOverlap($this->getKey())],
        ]);

        return $rules;
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
        $start = $startedAt instanceof DateTimeInterface ? $startedAt : \Illuminate\Support\Facades\Date::parse($startedAt);
        $end = $endedAt === null
            ? null
            : ($endedAt instanceof DateTimeInterface ? $endedAt : \Illuminate\Support\Facades\Date::parse($endedAt));

        if ($end instanceof DateTimeInterface) {
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
}
