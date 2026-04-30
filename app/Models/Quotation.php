<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\QuoteStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Override;

/**
 * @mixin IdeHelperQuotation
 */
class Quotation extends Model
{
    use BelongsToCompany;
    use HasLocks;
    use HasValidity;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'opportunity_id',
        'currency',
        'notes',
        'status',
        'version',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:opportunities,id'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'string', QuoteStatus::validationRule()],
            'version' => ['sometimes', 'integer', 'min:0', 'max:255'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:opportunities,id'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', QuoteStatus::validationRule()],
            'version' => ['sometimes', 'integer', 'min:0', 'max:255'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'version' => 'integer',
        ];
    }
}
