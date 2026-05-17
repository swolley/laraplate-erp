<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\QuoteStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Observers\QuotationObserver;
use Override;

#[ObservedBy([QuotationObserver::class])]
/**
 * @mixin \Eloquent
 * @mixin IdeHelperQuotation
 */
final class Quotation extends Model
{
    use BelongsToCompany;
    use HasLocks;
    use HasValidity;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::Quotations->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'party_id',
        'opportunity_id',
        'currency',
        'notes',
        'status',
        'version',
    ];

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /**
     * @return HasMany<QuotationItem, $this>
     */
    public function quotation_items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    /**
     * @return HasMany<SalesOrder, $this>
     */
    public function sales_orders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'party_id' => ['required', 'integer', 'exists:' . ERPTables::Parties->value . ',id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:' . ERPTables::Opportunities->value . ',id'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'string', QuoteStatus::validationRule()],
            'version' => ['sometimes', 'integer', 'min:0', 'max:255'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'party_id' => ['sometimes', 'integer', 'exists:' . ERPTables::Parties->value . ',id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:' . ERPTables::Opportunities->value . ',id'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', QuoteStatus::validationRule()],
            'version' => ['sometimes', 'integer', 'min:0', 'max:255'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::saving(static function (Quotation $quotation): void {
            if ($quotation->party_id !== null) {
                $party = Party::query()->find($quotation->party_id);

                if ($party !== null && ! $party->is_customer) {
                    throw ValidationException::withMessages([
                        'party_id' => ['The selected party must be a customer.'],
                    ]);
                }
            }

            if ($quotation->opportunity_id === null) {
                return;
            }

            $opportunity = Opportunity::query()->find($quotation->opportunity_id);

            if ($opportunity === null) {
                throw ValidationException::withMessages([
                    'opportunity_id' => ['The selected opportunity is invalid.'],
                ]);
            }

            if ((int) $opportunity->party_id !== (int) $quotation->party_id) {
                throw ValidationException::withMessages([
                    'opportunity_id' => ['The opportunity must belong to the same party as this quotation.'],
                ]);
            }

            if ((int) $opportunity->company_id !== (int) $quotation->company_id) {
                throw ValidationException::withMessages([
                    'opportunity_id' => ['The opportunity must belong to the same company as this quotation.'],
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'version' => 'integer',
        ];
    }
}
