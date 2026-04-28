<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * Sub-year accounting bucket (typically a calendar month).
 *
 * Scoped through {@see FiscalYear} for multi-company isolation.
 *
 * @mixin IdeHelperFiscalPeriod
 */
class FiscalPeriod extends Model
{
    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

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

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
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
