<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\OpportunityStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * CRM opportunity (M3.1): qualified deal linked to a {@see Party} and pipeline {@see OpportunityStage}.
 *
 * @mixin \Eloquent
 * @mixin IdeHelperOpportunity
 */
final class Opportunity extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::Opportunities->value;

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'lead_id',
        'party_id',
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
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
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
            'company_id' => ['required', 'integer', 'exists:' . ERPTables::Companies->value . ',id'],
            'lead_id' => ['nullable', 'integer', 'exists:' . ERPTables::Leads->value . ',id'],
            'party_id' => ['required', 'integer', 'exists:' . ERPTables::Parties->value . ',id'],
            'stage_taxonomy_id' => ['required', 'integer', 'exists:' . CoreTables::Taxonomies->value . ',id'],
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
            'lead_id' => ['nullable', 'integer', 'exists:' . ERPTables::Leads->value . ',id'],
            'party_id' => ['sometimes', 'integer', 'exists:' . ERPTables::Parties->value . ',id'],
            'stage_taxonomy_id' => ['sometimes', 'integer', 'exists:' . CoreTables::Taxonomies->value . ',id'],
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

    protected static function booted(): void
    {
        self::saving(static function (Opportunity $opportunity): void {
            if ($opportunity->party_id === null) {
                return;
            }

            $party = Party::query()->find($opportunity->party_id);

            if ($party !== null && ! $party->is_customer) {
                throw ValidationException::withMessages([
                    'party_id' => ['The selected party must be a customer.'],
                ]);
            }
        });
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
