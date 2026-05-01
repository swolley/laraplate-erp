<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;

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
