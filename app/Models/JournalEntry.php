<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Business\Concerns\BelongsToCompany;
use Modules\Core\Overrides\Model;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * Header for a posted double-entry journal voucher.
 *
 * @mixin IdeHelperJournalEntry
 */
class JournalEntry extends Model
{
    use BelongsToCompany;

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    protected $fillable = [
        'company_id',
        'fiscal_period_id',
        'posted_at',
        'posted_by',
        'reference_type',
        'reference_id',
        'description',
    ];

    /**
     * @return BelongsTo<FiscalPeriod, $this>
     */
    public function fiscal_period(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    /**
     * @return HasMany<JournalEntryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id');
    }

    /**
     * @return MorphTo<EloquentModel, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'fiscal_period_id' => ['nullable', 'integer', 'exists:fiscal_periods,id'],
            'posted_at' => ['nullable', 'date'],
            'posted_by' => ['nullable', 'integer'],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'fiscal_period_id' => ['nullable', 'integer', 'exists:fiscal_periods,id'],
            'posted_at' => ['nullable', 'date'],
            'posted_by' => ['nullable', 'integer'],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'posted_at' => 'immutable_datetime',
        ];
    }
}
