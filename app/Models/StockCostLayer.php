<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Override;

/**
 * @mixin IdeHelperStockCostLayer
 */
class StockCostLayer extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'item_id',
        'warehouse_id',
        'stock_movement_id',
        'qty_remaining',
        'unit_cost',
    ];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'qty_remaining' => 'integer',
            'unit_cost' => 'decimal:4',
        ];
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
     * @return BelongsTo<StockMovement, $this>
     */
    public function stock_movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    protected function shouldVersioning(): bool
    {
        return false;
    }
}
