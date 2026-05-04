<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;

/**
 * @mixin IdeHelperWarehouse
 */
class Warehouse extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'site_id',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<StockLevel, $this>
     */
    public function stock_levels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }
}
