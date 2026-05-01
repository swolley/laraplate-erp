<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;

/**
 * Line on a {@see GoodsReceipt} driving stock-in and optional PO receipt progress.
 *
 * @mixin IdeHelperGoodsReceiptLine
 */
class GoodsReceiptLine extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'goods_receipt_id',
        'item_id',
        'warehouse_id',
        'quantity',
        'unit_cost',
        'purchase_order_line_id',
    ];

    /**
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goods_receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<PurchaseOrderLine, $this>
     */
    public function purchase_order_line(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    protected function shouldVersioning(): bool
    {
        return false;
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:4',
        ];
    }
}
