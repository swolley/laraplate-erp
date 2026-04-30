<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;

class PurchaseOrder extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'customer_id',
        'reference',
        'currency',
        'status',
        'ordered_at',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
