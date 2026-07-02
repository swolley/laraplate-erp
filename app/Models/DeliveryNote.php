<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Casts\DeliveryNoteDirection;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Observers\DeliveryNoteObserver;
use Override;

/**
 * @property int|string $id
 * @property int $company_id
 * @property int|null $sales_order_id
 * @property DeliveryNoteDirection $direction
 * @property string|null $reference
 * @property \Carbon\CarbonInterface|null $delivered_at
 * @property \Carbon\CarbonInterface|null $posted_at
 * @property \Carbon\CarbonInterface|null $inventory_posted_at
 * @property int|null $cogs_journal_entry_id
 * @property string|null $notes
 * @mixin \Eloquent
 * @mixin IdeHelperDeliveryNote
 */
#[ObservedBy([DeliveryNoteObserver::class])]
final class DeliveryNote extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::DeliveryNotes->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'sales_order_id',
        'direction',
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
            'direction' => DeliveryNoteDirection::class,
            'delivered_at' => 'datetime',
            'posted_at' => 'datetime',
            'inventory_posted_at' => 'datetime',
        ];
    }
}
