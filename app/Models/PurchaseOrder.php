<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Override;

/**
 * @mixin IdeHelperPurchaseOrder
 */
class PurchaseOrder extends Model
{
    use BelongsToCompany;

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
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'party_id' => ['required', 'integer', 'exists:parties,id'],
            'reference' => ['nullable', 'string', 'max:64'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', 'string', 'in:draft,confirmed,partial,received'],
            'ordered_at' => ['nullable', 'date'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'party_id' => ['sometimes', 'integer', 'exists:parties,id'],
            'reference' => ['nullable', 'string', 'max:64'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', 'string', 'in:draft,confirmed,partial,received'],
            'ordered_at' => ['nullable', 'date'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        static::saving(static function (PurchaseOrder $purchase_order): void {
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
