<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Override;

/**
 * Line item on a {@see SalesOrder}.
 *
 * @mixin IdeHelperSalesOrderLine
 */
class SalesOrderLine extends Model
{
    use HasLocks;

    protected $table = 'sales_order_lines';

    protected $fillable = [
        'sales_order_id',
        'quotation_item_id',
        'name',
        'qty_ordered',
        'qty_delivered',
        'qty_invoiced',
        'unit_price',
        'status',
    ];

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function sales_order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * @return BelongsTo<QuotationItem, $this>
     */
    public function quotation_item(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'sales_order_id' => ['required', 'integer', 'exists:sales_orders,id'],
            'quotation_item_id' => ['nullable', 'integer', 'exists:quotations_items,id'],
            'name' => ['required', 'string', 'max:255'],
            'qty_ordered' => ['required', 'integer', 'min:1'],
            'qty_delivered' => ['sometimes', 'integer', 'min:0'],
            'qty_invoiced' => ['sometimes', 'integer', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', SalesOrderLineStatus::validationRule()],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'sales_order_id' => ['sometimes', 'integer', 'exists:sales_orders,id'],
            'quotation_item_id' => ['nullable', 'integer', 'exists:quotations_items,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'qty_ordered' => ['sometimes', 'integer', 'min:1'],
            'qty_delivered' => ['sometimes', 'integer', 'min:0'],
            'qty_invoiced' => ['sometimes', 'integer', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', SalesOrderLineStatus::validationRule()],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'status' => SalesOrderLineStatus::class,
            'qty_ordered' => 'integer',
            'qty_delivered' => 'integer',
            'qty_invoiced' => 'integer',
            'unit_price' => 'decimal:4',
        ];
    }
}
