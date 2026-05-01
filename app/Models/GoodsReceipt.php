<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Observers\GoodsReceiptObserver;

/**
 * @mixin IdeHelperGoodsReceipt
 */
#[ObservedBy([GoodsReceiptObserver::class])]
class GoodsReceipt extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'reference',
        'received_at',
        'posted_at',
        'inventory_posted_at',
        'notes',
    ];

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchase_order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return HasMany<GoodsReceiptLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'posted_at' => 'datetime',
            'inventory_posted_at' => 'datetime',
        ];
    }
}
