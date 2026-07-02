<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @property int|string $id
 * @property int $company_id
 * @property int $party_id
 * @property int|null $purchase_order_id
 * @property int|null $debit_note_invoice_id
 * @property int|null $delivery_note_id
 * @property string|null $reference
 * @property ReturnStatus $status
 * @property \Carbon\CarbonInterface|null $processed_at
 * @property string|null $notes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SupplierReturnLine> $lines
 * @mixin \Eloquent
 * @mixin IdeHelperSupplierReturn
 */
final class SupplierReturn extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::SupplierReturns->value;

    #[Override]
    protected $fillable = [
        'company_id',
        'party_id',
        'purchase_order_id',
        'debit_note_invoice_id',
        'delivery_note_id',
        'reference',
        'status',
        'processed_at',
        'notes',
    ];

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchase_order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function debit_note_invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'debit_note_invoice_id');
    }

    /**
     * @return BelongsTo<DeliveryNote, $this>
     */
    public function delivery_note(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    /**
     * @return HasMany<SupplierReturnLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplierReturnLine::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'processed_at' => 'immutable_datetime',
        ];
    }
}
