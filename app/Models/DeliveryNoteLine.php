<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;

/**
 * Line on a {@see DeliveryNote} driving stock-out and optional SO evasion.
 *
 * @mixin IdeHelperDeliveryNoteLine
 */
class DeliveryNoteLine extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'delivery_note_id',
        'item_id',
        'warehouse_id',
        'quantity',
        'sales_order_line_id',
    ];

    /**
     * @return BelongsTo<DeliveryNote, $this>
     */
    public function delivery_note(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
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
     * @return BelongsTo<SalesOrderLine, $this>
     */
    public function sales_order_line(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    protected function shouldVersioning(): bool
    {
        return false;
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }
}
