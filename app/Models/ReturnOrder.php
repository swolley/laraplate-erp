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
 * @mixin \Eloquent
 * @mixin IdeHelperReturnOrder
 */
final class ReturnOrder extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::ReturnOrders->value;

    #[Override]
    protected $fillable = [
        'company_id',
        'party_id',
        'invoice_id',
        'credit_note_invoice_id',
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
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function credit_note_invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'credit_note_invoice_id');
    }

    /**
     * @return BelongsTo<DeliveryNote, $this>
     */
    public function delivery_note(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    /**
     * @return HasMany<ReturnOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ReturnOrderLine::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'processed_at' => 'immutable_datetime',
        ];
    }
}
