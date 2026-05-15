<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\VatSettlementStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * Periodic VAT settlement (liquidazione IVA) for Italian compliance.
 *
 * @mixin \Eloquent
 * @mixin IdeHelperVatSettlement
 */
final class VatSettlement extends Model
{
    use BelongsToCompany;

    #[Override]
    protected $table = ERPTables::VatSettlements->value;

    /**
     * The attributes that are mass assignable.
     */
    #[\Override]
    protected $fillable = [
        'company_id',
        'fiscal_period_id',
        'vat_sales',
        'vat_purchases',
        'previous_credit',
        'settlement_amount',
        'status',
        'confirmed_at',
        'confirmed_by',
    ];

    /**
     * @return BelongsTo<FiscalPeriod, $this>
     */
    public function fiscal_period(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:'.ERPTables::Companies->value.',id'],
            'fiscal_period_id' => ['required', 'integer', 'exists:'.ERPTables::FiscalPeriods->value.',id'],
            'vat_sales' => ['sometimes', 'numeric'],
            'vat_purchases' => ['sometimes', 'numeric'],
            'previous_credit' => ['sometimes', 'numeric'],
            'settlement_amount' => ['sometimes', 'numeric'],
            'status' => ['sometimes', 'string', VatSettlementStatus::validationRule()],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'fiscal_period_id' => ['sometimes', 'integer', 'exists:'.ERPTables::FiscalPeriods->value.',id'],
            'vat_sales' => ['sometimes', 'numeric'],
            'vat_purchases' => ['sometimes', 'numeric'],
            'previous_credit' => ['sometimes', 'numeric'],
            'settlement_amount' => ['sometimes', 'numeric'],
            'status' => ['sometimes', 'string', VatSettlementStatus::validationRule()],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::saving(static function (VatSettlement $settlement): void {
            if (! $settlement->exists) {
                return;
            }

            if (VatSettlementStatus::Confirmed->value !== $settlement->getOriginal('status')) {
                return;
            }

            if (! $settlement->isDirty()) {
                return;
            }

            throw ValidationException::withMessages([
                'status' => ['A confirmed VAT settlement cannot be modified.'],
            ]);
        });
    }

    protected function casts(): array
    {
        return [
            'vat_sales' => 'decimal:4',
            'vat_purchases' => 'decimal:4',
            'previous_credit' => 'decimal:4',
            'settlement_amount' => 'decimal:4',
            'status' => VatSettlementStatus::class,
            'confirmed_at' => 'immutable_datetime',
        ];
    }
}
