<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\VatRegisterType;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * Single-tax-code row inside an Italian VAT register (registro IVA).
 *
 * @mixin \Eloquent
 * @mixin IdeHelperVatRegisterEntry
 */
final class VatRegisterEntry extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::VatRegisterEntries->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'invoice_id',
        'register_type',
        'protocol_number',
        'registration_date',
        'fiscal_year_id',
        'tax_code_id',
        'taxable_amount',
        'tax_amount',
    ];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<FiscalYear, $this>
     */
    public function fiscal_year(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * @return BelongsTo<TaxCode, $this>
     */
    public function tax_code(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:' . ERPTables::Companies->value . ',id'],
            'invoice_id' => ['required', 'integer', 'exists:' . ERPTables::Invoices->value . ',id'],
            'register_type' => ['required', 'string', VatRegisterType::validationRule()],
            'protocol_number' => ['required', 'integer', 'min:1'],
            'registration_date' => ['required', 'date'],
            'fiscal_year_id' => ['required', 'integer', 'exists:' . ERPTables::FiscalYears->value . ',id'],
            'tax_code_id' => ['required', 'integer', 'exists:' . ERPTables::TaxCodes->value . ',id'],
            'taxable_amount' => ['required', 'numeric'],
            'tax_amount' => ['required', 'numeric'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'invoice_id' => ['sometimes', 'integer', 'exists:' . ERPTables::Invoices->value . ',id'],
            'register_type' => ['sometimes', 'string', VatRegisterType::validationRule()],
            'protocol_number' => ['sometimes', 'integer', 'min:1'],
            'registration_date' => ['sometimes', 'date'],
            'fiscal_year_id' => ['sometimes', 'integer', 'exists:' . ERPTables::FiscalYears->value . ',id'],
            'tax_code_id' => ['sometimes', 'integer', 'exists:' . ERPTables::TaxCodes->value . ',id'],
            'taxable_amount' => ['sometimes', 'numeric'],
            'tax_amount' => ['sometimes', 'numeric'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'register_type' => VatRegisterType::class,
            'registration_date' => 'date',
            'taxable_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'protocol_number' => 'integer',
        ];
    }
}
