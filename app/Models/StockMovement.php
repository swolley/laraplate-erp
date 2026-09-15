<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Core\Contracts\RestrictsCrudWrites;
use Modules\Core\Models\Concerns\DeniesGenericCrudWrites;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\StockMovementDirection;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @property int|string $id
 * @property int $company_id
 * @property int $item_id
 * @property int $warehouse_id
 * @property StockMovementDirection $direction
 * @property numeric-string $quantity
 * @property numeric-string|null $unit_cost
 * @property string $source_type
 * @property int $source_id
 *
 * @mixin \Eloquent
 * @mixin IdeHelperStockMovement
 */
final class StockMovement extends Model implements RestrictsCrudWrites
{
    use BelongsToCompany;
    use DeniesGenericCrudWrites;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::StockMovements->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'item_id',
        'warehouse_id',
        'direction',
        'quantity',
        'unit_cost',
        'source_type',
        'source_id',
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
     * @return MorphTo<EloquentModel, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<StockCostLayer, $this>
     */
    public function cost_layers(): HasMany
    {
        return $this->hasMany(StockCostLayer::class);
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'direction' => ['required', 'string', StockMovementDirection::validationRule()],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'direction' => ['sometimes', 'string', StockMovementDirection::validationRule()],
            'quantity' => ['sometimes', 'numeric', 'min:0.0001'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
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
            'direction' => StockMovementDirection::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    protected function shouldVersioning(): bool
    {
        return false;
    }
}
