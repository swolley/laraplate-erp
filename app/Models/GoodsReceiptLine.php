<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * Line on a {@see GoodsReceipt} driving stock-in and optional PO receipt progress.
 *
 * @property int|string $id
 * @property int $company_id
 * @property int $goods_receipt_id
 * @property int $item_id
 * @property int $warehouse_id
 * @property numeric-string $quantity
 * @property numeric-string $qty_returned
 * @property numeric-string|null $unit_cost
 * @property int|null $purchase_order_line_id
 * @mixin \Eloquent
 * @mixin IdeHelperGoodsReceiptLine
 */
final class GoodsReceiptLine extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::GoodsReceiptLines->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'goods_receipt_id',
        'item_id',
        'warehouse_id',
        'quantity',
        'qty_returned',
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

    /**
     * @return array<string, mixed>
     */

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'qty_returned' => ['sometimes', 'numeric', 'min:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'quantity' => ['sometimes', 'numeric', 'min:0.0001'],
            'qty_returned' => ['sometimes', 'numeric', 'min:0'],
            'unit_cost' => ['sometimes', 'numeric', 'min:0'],
        ]);

        return $rules;
    }

    protected function shouldVersioning(): bool
    {
        return false;
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'qty_returned' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }
}
