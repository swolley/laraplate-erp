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
        'customer_id',
        'reference',
        'currency',
        'status',
        'ordered_at',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'reference' => ['nullable', 'string', 'max:64'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', 'string', 'in:draft,confirmed,partial,received'],
            'ordered_at' => ['nullable', 'date'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
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
            if ($purchase_order->customer_id === null) {
                return;
            }

            $customer = Customer::query()->find($purchase_order->customer_id);

            if ($customer === null) {
                return;
            }

            if ((int) $customer->company_id !== (int) $purchase_order->company_id) {
                throw ValidationException::withMessages([
                    'customer_id' => ['The customer must belong to the same company as this purchase order.'],
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
