<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\StockMovementDirection;
use Modules\ERP\Concerns\BelongsToCompany;
use Override;

class StockMovement extends Model
{
    use BelongsToCompany;

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
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'direction' => StockMovementDirection::class,
            'quantity' => 'integer',
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

    protected function shouldVersioning(): bool
    {
        return false;
    }
}
