<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\ERP\Casts\BillingMode;
use Modules\Core\Overrides\Model;
use Override;

/**
 * @mixin IdeHelperQuotationItem
 */
class QuotationItem extends Model
{
    /**
     * Table name matches migration `quotations_items`.
     */
    protected $table = 'quotations_items';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'quotation_id',
        'price_list_item_id',
        'name',
        'billing_mode',
        'quantity',
        'unit_price',
    ];

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<PriceListItem, $this>
     */
    public function price_list_item(): BelongsTo
    {
        return $this->belongsTo(PriceListItem::class);
    }

    protected function casts(): array
    {
        return [
            'billing_mode' => BillingMode::class,
            'quantity' => 'integer',
            'unit_price' => 'decimal:4',
        ];
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'quotation_id' => ['required', 'integer', 'exists:quotations,id'],
            'price_list_item_id' => ['nullable', 'integer', 'exists:price_list_items,id'],
            'name' => ['required', 'string', 'max:255'],
            'billing_mode' => ['required', 'string', BillingMode::validationRule()],
            'quantity' => ['required', 'integer', 'min:1', 'max:65535'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'quotation_id' => ['sometimes', 'integer', 'exists:quotations,id'],
            'price_list_item_id' => ['nullable', 'integer', 'exists:price_list_items,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'billing_mode' => ['sometimes', 'string', BillingMode::validationRule()],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        return $rules;
    }
}
