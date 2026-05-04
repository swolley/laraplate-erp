<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Modules\Core\Overrides\Model;
use Override;

/**
 * Line on a {@see PurchaseOrder} tracking ordered vs received quantities.
 *
 * @mixin IdeHelperPurchaseOrderLine
 */
class PurchaseOrderLine extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'item_id',
        'name',
        'qty_ordered',
        'qty_received',
        'unit_price',
    ];

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchase_order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'name' => ['required', 'string', 'max:255'],
            'qty_ordered' => ['required', 'integer', 'min:1'],
            'qty_received' => ['sometimes', 'integer', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'purchase_order_id' => ['sometimes', 'integer', 'exists:purchase_orders,id'],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'qty_ordered' => ['sometimes', 'integer', 'min:1'],
            'qty_received' => ['sometimes', 'integer', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        static::saving(static function (PurchaseOrderLine $line): void {
            $line_is_progressed = $line->qty_received > 0;

            if ($line->exists && $line_is_progressed && $line->isDirty('qty_ordered')) {
                throw ValidationException::withMessages([
                    'qty_ordered' => ['qty_ordered cannot be changed after goods have been received on this line.'],
                ]);
            }

            if ($line->item_id === null) {
                return;
            }

            $purchase_order = $line->purchase_order ?? PurchaseOrder::query()->find($line->purchase_order_id);

            if ($purchase_order === null) {
                return;
            }

            $item = Item::query()->find($line->item_id);

            if ($item === null) {
                throw ValidationException::withMessages([
                    'item_id' => ['The selected item is invalid.'],
                ]);
            }

            if ((int) $item->company_id !== (int) $purchase_order->company_id) {
                throw ValidationException::withMessages([
                    'item_id' => ['The item must belong to the same company as the purchase order.'],
                ]);
            }
        });
    }

    protected function shouldVersioning(): bool
    {
        return false;
    }

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'integer',
            'qty_received' => 'integer',
            'unit_price' => 'decimal:4',
        ];
    }
}
