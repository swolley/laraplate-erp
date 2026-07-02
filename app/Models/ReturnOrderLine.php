<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @property int|string $id
 * @property int $company_id
 * @property int $return_order_id
 * @property int|null $invoice_line_id
 * @property int|null $delivery_note_line_id
 * @property int $item_id
 * @property int $warehouse_id
 * @property numeric-string $quantity
 * @property numeric-string|null $unit_cost
 * @mixin \Eloquent
 * @mixin IdeHelperReturnOrderLine
 */
final class ReturnOrderLine extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::ReturnOrderLines->value;

    #[Override]
    protected $fillable = [
        'company_id',
        'return_order_id',
        'invoice_line_id',
        'delivery_note_line_id',
        'item_id',
        'warehouse_id',
        'quantity',
        'unit_cost',
    ];

    /**
     * @return BelongsTo<ReturnOrder, $this>
     */
    public function return_order(): BelongsTo
    {
        return $this->belongsTo(ReturnOrder::class);
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
     * @return BelongsTo<DeliveryNoteLine, $this>
     */
    public function delivery_note_line(): BelongsTo
    {
        return $this->belongsTo(DeliveryNoteLine::class);
    }

    /**
     * @return array<string, mixed>
     */

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'unit_cost' => ['sometimes', 'numeric', 'min:0'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'quantity' => ['sometimes', 'numeric', 'min:0.0001'],
            'unit_cost' => ['sometimes', 'numeric', 'min:0'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::creating(static function (ReturnOrderLine $line): void {
            if ($line->company_id !== null || $line->return_order_id === null) {
                return;
            }

            $company_id = ReturnOrder::query()->whereKey($line->return_order_id)->value('company_id');

            if (! is_int($company_id)) {
                return;
            }

            $line->company_id = $company_id;
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }
}
