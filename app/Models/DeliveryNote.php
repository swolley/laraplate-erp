<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;

class DeliveryNote extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'sales_order_id',
        'reference',
        'delivered_at',
        'notes',
    ];

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function sales_order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }
}
