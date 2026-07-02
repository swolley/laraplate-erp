<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Concerns\HasValidations;
use Modules\Core\Models\Concerns\HasVersions;
use Modules\ERP\Enums\ERPTables;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * Sub-year accounting bucket (typically a calendar month).
 *
 * Scoped through {@see FiscalYear} for multi-company isolation.
 *
 * @property int|string $id
 * @property int $fiscal_year_id
 * @property int $period_no
 * @property \Carbon\CarbonInterface $start_date
 * @property \Carbon\CarbonInterface $end_date
 * @property bool $is_closed
 * @property-read FiscalYear|null $fiscal_year
 * @mixin \Eloquent
 * @mixin IdeHelperFiscalPeriod
 */
final class FiscalPeriod extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory;
    use HasValidations {
        HasValidations::getRules as private validationsHasRules;
    }
    use HasVersions;

    /**
     * Accounting models always version with DIFF; overrides any Setting row.
     */
    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::FiscalPeriods->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'fiscal_year_id',
        'period_no',
        'start_date',
        'end_date',
        'is_closed',
    ];

    /**
     * @return BelongsTo<FiscalYear, $this>
     */
    public function fiscal_year(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        $rules = $this->validationsHasRules();
        $rules['create'] = array_merge($rules['create'], [
            'fiscal_year_id' => ['required', 'integer', 'exists:' . ERPTables::FiscalYears->value . ',id'],
            'period_no' => ['required', 'integer', 'min:1', 'max:366'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_closed' => ['sometimes', 'boolean'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'period_no' => ['sometimes', 'integer', 'min:1', 'max:366'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'is_closed' => ['sometimes', 'boolean'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'period_no' => 'integer',
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'is_closed' => 'boolean',
        ];
    }
}
