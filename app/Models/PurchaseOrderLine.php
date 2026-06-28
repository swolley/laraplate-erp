<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Modules\Core\Overrides\Model;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * Line on a {@see PurchaseOrder} tracking ordered vs received quantities.
 *
 * @property int|string $id
 * @property int $purchase_order_id
 * @property int|null $item_id
 * @property string $name
 * @property numeric-string $qty_ordered
 * @property numeric-string $qty_received
 * @property numeric-string $qty_returned
 * @property numeric-string|null $unit_price
 *
 * @mixin \Eloquent
 * @mixin IdeHelperPurchaseOrderLine
 */
final class PurchaseOrderLine extends Model
{
    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::PurchaseOrderLines->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'purchase_order_id',
        'item_id',
        'name',
        'qty_ordered',
        'qty_received',
        'qty_returned',
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

    /**
     * @return array<string, mixed>
     */

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'purchase_order_id' => ['required', 'integer', 'exists:' . ERPTables::PurchaseOrders->value . ',id'],
            'item_id' => ['nullable', 'integer', 'exists:' . ERPTables::Items->value . ',id'],
            'name' => ['required', 'string', 'max:255'],
            'qty_ordered' => ['required', 'numeric', 'min:0.0001'],
            'qty_received' => ['sometimes', 'numeric', 'min:0'],
            'qty_returned' => ['sometimes', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'purchase_order_id' => ['sometimes', 'integer', 'exists:' . ERPTables::PurchaseOrders->value . ',id'],
            'item_id' => ['nullable', 'integer', 'exists:' . ERPTables::Items->value . ',id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'qty_ordered' => ['sometimes', 'numeric', 'min:0.0001'],
            'qty_received' => ['sometimes', 'numeric', 'min:0'],
            'qty_returned' => ['sometimes', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::saving(static function (PurchaseOrderLine $line): void {
            $line_is_progressed = $line->qty_received > 0;

            if ($line->exists && $line_is_progressed && $line->isDirty('qty_ordered')) {
                throw ValidationException::withMessages([
                    'qty_ordered' => ['qty_ordered cannot be changed after goods have been received on this line.'],
                ]);
            }

            if ($line->item_id === null) {
                return;
            }

            $purchase_order = $line->purchase_order ?? PurchaseOrder::query()->whereKey($line->purchase_order_id)->first();

            if (! $purchase_order instanceof PurchaseOrder) {
                return;
            }

            $item = Item::query()->find($line->item_id);

            if ($item === null) {
                throw ValidationException::withMessages([
                    'item_id' => ['The selected item is invalid.'],
                ]);
            }

            if ($item->company_id !== $purchase_order->company_id) {
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
            'qty_ordered' => 'decimal:4',
            'qty_received' => 'decimal:4',
            'qty_returned' => 'decimal:4',
            'unit_price' => 'decimal:4',
        ];
    }
}
