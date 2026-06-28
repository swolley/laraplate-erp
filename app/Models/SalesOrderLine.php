<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * Line item on a {@see SalesOrder}.
 *
 * @property int|string $id
 * @property int $sales_order_id
 * @property int|null $quotation_item_id
 * @property int|null $item_id
 * @property string $name
 * @property numeric-string $qty_ordered
 * @property numeric-string $qty_delivered
 * @property numeric-string $qty_invoiced
 * @property numeric-string $qty_returned
 * @property numeric-string|null $unit_price
 * @property SalesOrderLineStatus $status
 * @property-read SalesOrder|null $sales_order
 *
 * @mixin \Eloquent
 * @mixin IdeHelperSalesOrderLine
 */
final class SalesOrderLine extends Model
{
    use HasLocks;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::SalesOrderLines->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'sales_order_id',
        'quotation_item_id',
        'item_id',
        'name',
        'qty_ordered',
        'qty_delivered',
        'qty_invoiced',
        'qty_returned',
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
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<QuotationItem, $this>
     */
    public function quotation_item(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class);
    }

    /**
     * @return array<string, mixed>
     */

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'sales_order_id' => ['required', 'integer', 'exists:' . ERPTables::SalesOrders->value . ',id'],
            'quotation_item_id' => ['nullable', 'integer', 'exists:' . ERPTables::QuotationItems->value . ',id'],
            'item_id' => ['nullable', 'integer', 'exists:' . ERPTables::Items->value . ',id'],
            'name' => ['required', 'string', 'max:255'],
            'qty_ordered' => ['required', 'numeric', 'min:0.0001'],
            'qty_delivered' => ['sometimes', 'numeric', 'min:0'],
            'qty_invoiced' => ['sometimes', 'numeric', 'min:0'],
            'qty_returned' => ['sometimes', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', SalesOrderLineStatus::validationRule()],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'sales_order_id' => ['sometimes', 'integer', 'exists:' . ERPTables::SalesOrders->value . ',id'],
            'quotation_item_id' => ['nullable', 'integer', 'exists:' . ERPTables::QuotationItems->value . ',id'],
            'item_id' => ['nullable', 'integer', 'exists:' . ERPTables::Items->value . ',id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'qty_ordered' => ['sometimes', 'numeric', 'min:0.0001'],
            'qty_delivered' => ['sometimes', 'numeric', 'min:0'],
            'qty_invoiced' => ['sometimes', 'numeric', 'min:0'],
            'qty_returned' => ['sometimes', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', SalesOrderLineStatus::validationRule()],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::saving(static function (SalesOrderLine $line): void {
            $line_is_progressed = $line->qty_delivered > 0 || $line->qty_invoiced > 0;

            if (! $line->exists || ! $line_is_progressed) {
                return;
            }

            if (! $line->isDirty('qty_ordered')) {
                return;
            }

            throw ValidationException::withMessages([
                'qty_ordered' => ['qty_ordered cannot be changed after delivery or invoicing has started.'],
            ]);
        });
    }

    protected function casts(): array
    {
        return [
            'status' => SalesOrderLineStatus::class,
            'qty_ordered' => 'decimal:4',
            'qty_delivered' => 'decimal:4',
            'qty_invoiced' => 'decimal:4',
            'qty_returned' => 'decimal:4',
            'unit_price' => 'decimal:4',
        ];
    }
}
