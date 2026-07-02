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
 * @property numeric-string $quantity
 * @property numeric-string $weighted_avg_cost
 * @property-read Item|null $item
 * @property-read Warehouse|null $warehouse
 * @mixin \Eloquent
 * @mixin IdeHelperStockLevel
 */
final class StockLevel extends Model implements RestrictsCrudWrites
{
    use BelongsToCompany;
    use DeniesGenericCrudWrites;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::StockLevels->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'item_id',
        'warehouse_id',
        'quantity',
        'weighted_avg_cost',
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
     * @return array<string, mixed>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'weighted_avg_cost' => ['sometimes', 'numeric', 'min:0'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'weighted_avg_cost' => ['sometimes', 'numeric', 'min:0'],
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
            'quantity' => 'decimal:4',
            'weighted_avg_cost' => 'decimal:4',
        ];
    }
}
