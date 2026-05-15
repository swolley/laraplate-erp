<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\PurchaseOrderStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperPurchaseOrder
 */
final class PurchaseOrder extends Model
{
    use BelongsToCompany;

    #[Override]
    protected $table = ERPTables::PurchaseOrders->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'party_id',
        'reference',
        'currency',
        'status',
        'ordered_at',
    ];

    /**
     * @return BelongsTo<Party,$this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Party::class)->where('is_supplier', true);
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:'.ERPTables::Companies->value.',id'],
            'party_id' => ['required', 'integer', 'exists:'.ERPTables::Parties->value.',id'],
            'reference' => ['nullable', 'string', 'max:64'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', PurchaseOrderStatus::validationRule()],
            'ordered_at' => ['nullable', 'date'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'party_id' => ['sometimes', 'integer', 'exists:'.ERPTables::Parties->value.',id'],
            'reference' => ['nullable', 'string', 'max:64'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', PurchaseOrderStatus::validationRule()],
            'ordered_at' => ['nullable', 'date'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::saving(static function (PurchaseOrder $purchase_order): void {
            if ($purchase_order->party_id === null) {
                return;
            }

            $party = Party::query()->find($purchase_order->party_id);

            if ($party === null) {
                return;
            }

            if (! $party->is_supplier) {
                throw ValidationException::withMessages([
                    'party_id' => ['The selected party must be a supplier.'],
                ]);
            }

            if ((int) $party->company_id !== (int) $purchase_order->company_id) {
                throw ValidationException::withMessages([
                    'party_id' => ['The party must belong to the same company as this purchase order.'],
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
        ];
    }
}
