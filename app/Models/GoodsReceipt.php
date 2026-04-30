<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;

class GoodsReceipt extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'reference',
        'received_at',
        'notes',
    ];

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchase_order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
