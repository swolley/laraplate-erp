<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\Pivot\InvoiceLineHasDeliveryNoteLine;
use Override;

/**
 * Line on a {@see DeliveryNote} driving stock movement and optional SO evasion.
 *
 * @property int|string $id
 * @property int $company_id
 * @property int $delivery_note_id
 * @property int $item_id
 * @property int $warehouse_id
 * @property numeric-string $quantity
 * @property int|null $sales_order_line_id
 * @property-read DeliveryNote|null $delivery_note
 * @property-read \Illuminate\Database\Eloquent\Relations\Pivot|null $pivot
 * @mixin \Eloquent
 * @mixin IdeHelperDeliveryNoteLine
 */
final class DeliveryNoteLine extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::DeliveryNoteLines->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
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

    /**
     * @return BelongsToMany<InvoiceLine, $this>
     */
    public function invoice_lines(): BelongsToMany
    {
        return $this->belongsToMany(
            InvoiceLine::class,
            ERPTables::InvoiceLineDeliveryNoteLine->value,
        )->using(InvoiceLineHasDeliveryNoteLine::class)->withPivot('quantity')->withTimestamps();
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
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'quantity' => ['sometimes', 'numeric', 'min:0.0001'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::saved(static function (DeliveryNoteLine $line): void {
            if ($line->sales_order_line_id === null) {
                return;
            }

            $sales_order_line = SalesOrderLine::query()->find($line->sales_order_line_id);

            if ($sales_order_line !== null && ! $sales_order_line->isLocked()) {
                $sales_order_line->lock();
            }
        });
    }

    protected function shouldVersioning(): bool
    {
        return false;
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }
}
