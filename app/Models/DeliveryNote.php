<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Observers\DeliveryNoteObserver;

/**
 * @mixin IdeHelperDeliveryNote
 */
#[ObservedBy([DeliveryNoteObserver::class])]
class DeliveryNote extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'sales_order_id',
        'reference',
        'delivered_at',
        'posted_at',
        'inventory_posted_at',
        'cogs_journal_entry_id',
        'notes',
    ];

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function sales_order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * @return HasMany<DeliveryNoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryNoteLine::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function cogs_journal_entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'cogs_journal_entry_id');
    }

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'posted_at' => 'datetime',
            'inventory_posted_at' => 'datetime',
        ];
    }
}
