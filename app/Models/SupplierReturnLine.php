<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @property int|string $id
 * @property int $company_id
 * @property int $supplier_return_id
 * @property int|null $purchase_order_line_id
 * @property int|null $goods_receipt_line_id
 * @property int|null $delivery_note_line_id
 * @property int $item_id
 * @property int $warehouse_id
 * @property numeric-string $quantity
 * @mixin \Eloquent
 * @mixin IdeHelperSupplierReturnLine
 */
final class SupplierReturnLine extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::SupplierReturnLines->value;

    #[Override]
    protected $fillable = [
        'company_id',
        'supplier_return_id',
        'purchase_order_line_id',
        'goods_receipt_line_id',
        'delivery_note_line_id',
        'item_id',
        'warehouse_id',
        'quantity',
    ];

    /**
     * @return BelongsTo<SupplierReturn, $this>
     */
    public function supplier_return(): BelongsTo
    {
        return $this->belongsTo(SupplierReturn::class);
    }

    /**
     * @return BelongsTo<PurchaseOrderLine, $this>
     */
    public function purchase_order_line(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /**
     * @return BelongsTo<GoodsReceiptLine, $this>
     */
    public function goods_receipt_line(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class);
    }

    /**
     * @return BelongsTo<DeliveryNoteLine, $this>
     */
    public function delivery_note_line(): BelongsTo
    {
        return $this->belongsTo(DeliveryNoteLine::class);
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
     * @return array<string, mixed>
     */

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'quantity' => ['required', 'numeric', 'min:0.0001'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'quantity' => ['sometimes', 'numeric', 'min:0.0001'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::creating(static function (SupplierReturnLine $line): void {
            if ($line->company_id !== null || $line->supplier_return_id === null) {
                return;
            }

            $company_id = SupplierReturn::query()->whereKey($line->supplier_return_id)->value('company_id');

            if (! is_int($company_id)) {
                return;
            }

            $line->company_id = $company_id;
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }
}
