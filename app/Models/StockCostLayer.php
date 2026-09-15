<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Contracts\RestrictsCrudWrites;
use Modules\Core\Models\Concerns\DeniesGenericCrudWrites;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @property int|string $id
 * @property int $company_id
 * @property int $item_id
 * @property int $warehouse_id
 * @property int $stock_movement_id
 * @property numeric-string $qty_remaining
 * @property numeric-string $unit_cost
 *
 * @mixin \Eloquent
 * @mixin IdeHelperStockCostLayer
 */
final class StockCostLayer extends Model implements RestrictsCrudWrites
{
    use BelongsToCompany;
    use DeniesGenericCrudWrites;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::StockCostLayers->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'item_id',
        'warehouse_id',
        'stock_movement_id',
        'qty_remaining',
        'unit_cost',
    ];

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

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'qty_remaining' => ['required', 'numeric', 'min:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'qty_remaining' => ['sometimes', 'numeric', 'min:0'],
            'unit_cost' => ['sometimes', 'numeric', 'min:0'],
        ]);

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'qty_remaining' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    protected function shouldVersioning(): bool
    {
        return false;
    }
}
