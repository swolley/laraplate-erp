<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\OpportunityStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * CRM opportunity (M3.1): qualified deal linked to a {@see Customer} and pipeline {@see OpportunityStage}.
 *
 * @mixin IdeHelperOpportunity
 */
class Opportunity extends Model
{
    use BelongsToCompany;

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    protected $fillable = [
        'company_id',
        'lead_id',
        'customer_id',
        'stage_taxonomy_id',
        'name',
        'status',
        'expected_close_date',
        'expected_value_doc',
        'expected_currency_doc',
        'expected_value_local',
        'expected_currency_local',
        'expected_fx_rate',
        'probability',
        'won_at',
        'lost_at',
        'lost_reason',
    ];

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<OpportunityStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(OpportunityStage::class, 'stage_taxonomy_id');
    }

    /**
     * @return HasMany<Quotation, $this>
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'stage_taxonomy_id' => ['required', 'integer', 'exists:taxonomies,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', OpportunityStatus::validationRule()],
            'expected_close_date' => ['nullable', 'date'],
            'expected_value_doc' => ['nullable', 'numeric', 'min:0'],
            'expected_currency_doc' => ['required', 'string', 'size:3'],
            'expected_value_local' => ['nullable', 'numeric', 'min:0'],
            'expected_currency_local' => ['required', 'string', 'size:3'],
            'expected_fx_rate' => ['required', 'numeric', 'min:0'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'won_at' => ['nullable', 'date'],
            'lost_at' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'stage_taxonomy_id' => ['sometimes', 'integer', 'exists:taxonomies,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', OpportunityStatus::validationRule()],
            'expected_close_date' => ['nullable', 'date'],
            'expected_value_doc' => ['nullable', 'numeric', 'min:0'],
            'expected_currency_doc' => ['sometimes', 'string', 'size:3'],
            'expected_value_local' => ['nullable', 'numeric', 'min:0'],
            'expected_currency_local' => ['sometimes', 'string', 'size:3'],
            'expected_fx_rate' => ['sometimes', 'numeric', 'min:0'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'won_at' => ['nullable', 'date'],
            'lost_at' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'status' => OpportunityStatus::class,
            'expected_close_date' => 'immutable_date',
            'expected_value_doc' => 'decimal:4',
            'expected_value_local' => 'decimal:4',
            'expected_fx_rate' => 'decimal:8',
            'probability' => 'integer',
            'won_at' => 'immutable_datetime',
            'lost_at' => 'immutable_datetime',
        ];
    }
}
