<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\Core\Overrides\Model;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * Fiscal year boundary for accounting closes and reporting.
 *
 * @mixin IdeHelperFiscalYear
 */
class FiscalYear extends Model
{
    use BelongsToCompany;

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    protected $fillable = [
        'company_id',
        'year',
        'start_date',
        'end_date',
        'is_closed',
    ];

    /**
     * @return HasMany<FiscalPeriod, $this>
     */
    public function fiscal_periods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_closed' => ['sometimes', 'boolean'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'year' => ['sometimes', 'integer', 'min:1900', 'max:2100'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'is_closed' => ['sometimes', 'boolean'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'is_closed' => 'boolean',
        ];
    }
}
