<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Core\Contracts\RestrictsCrudWrites;
use Modules\Core\Models\Concerns\DeniesGenericCrudWrites;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Exceptions\PostedJournalImmutableException;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * Header for a posted double-entry journal voucher.
 *
 * @property int|string $id
 * @property int $company_id
 * @property int|null $fiscal_period_id
 * @property \Carbon\CarbonInterface|null $posted_at
 * @property int|null $posted_by
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $description
 * @property int|null $reverses_journal_entry_id
 * @property string|null $reversal_reason
 * @property-read \Illuminate\Database\Eloquent\Collection<int, JournalEntryLine> $lines
 * @property-read FiscalPeriod|null $fiscal_period
 * @mixin \Eloquent
 * @mixin IdeHelperJournalEntry
 */
final class JournalEntry extends Model implements RestrictsCrudWrites
{
    use BelongsToCompany;
    use DeniesGenericCrudWrites;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::JournalEntries->value;

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'fiscal_period_id',
        'posted_at',
        'posted_by',
        'reference_type',
        'reference_id',
        'description',
        'reverses_journal_entry_id',
        'reversal_reason',
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
     * Original posted entry that this voucher reverses (null for normal entries).
     *
     * @return BelongsTo<JournalEntry, $this>
     */
    public function original_entry_reversed(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_journal_entry_id');
    }

    /**
     * Reversal voucher targeting this entry, when one exists.
     *
     * @return HasOne<JournalEntry, $this>
     */
    public function reversal_voucher(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_journal_entry_id');
    }

    /**
     * @return MorphTo<EloquentModel, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:' . ERPTables::Companies->value . ',id'],
            'fiscal_period_id' => ['nullable', 'integer', 'exists:' . ERPTables::FiscalPeriods->value . ',id'],
            'posted_at' => ['nullable', 'date'],
            'posted_by' => ['nullable', 'integer'],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'reverses_journal_entry_id' => ['nullable', 'integer', 'exists:' . ERPTables::JournalEntries->value . ',id'],
            'reversal_reason' => ['nullable', 'string'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'fiscal_period_id' => ['nullable', 'integer', 'exists:' . ERPTables::FiscalPeriods->value . ',id'],
            'posted_at' => ['nullable', 'date'],
            'posted_by' => ['nullable', 'integer'],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'reverses_journal_entry_id' => ['nullable', 'integer', 'exists:' . ERPTables::JournalEntries->value . ',id'],
            'reversal_reason' => ['nullable', 'string'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::updating(function (JournalEntry $entry): void {
            if ($entry->getOriginal('posted_at') === null) {
                return;
            }

            if ($entry->isDirty('deleted_at')) {
                throw PostedJournalImmutableException::make();
            }

            $dirty_keys = array_keys($entry->getDirty());

            if (array_diff($dirty_keys, ['updated_at']) !== []) {
                throw PostedJournalImmutableException::make();
            }
        });

        self::deleting(function (JournalEntry $entry): void {
            if ($entry->posted_at === null) {
                return;
            }

            throw PostedJournalImmutableException::make();
        });
    }

    protected function casts(): array
    {
        return [
            'posted_at' => 'immutable_datetime',
        ];
    }
}
