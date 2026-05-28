<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperSupplierReturnLine
 */
final class SupplierReturnLine extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::SupplierReturnLines->value;

    #[Override]
    protected $fillable = [
        'company_id',
        'supplier_return_id',
        'item_id',
        'warehouse_id',
        'quantity',
    ];

    /**
     * @return BelongsTo<SupplierReturn, $this>
     */
    public function supplier_return(): BelongsTo
    {
        return $this->belongsTo(SupplierReturn::class);
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

    protected static function booted(): void
    {
        self::creating(static function (SupplierReturnLine $line): void {
            if ($line->company_id !== null || $line->supplier_return_id === null) {
                return;
            }

            $line->company_id = SupplierReturn::query()->whereKey($line->supplier_return_id)->value('company_id');
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }
}
