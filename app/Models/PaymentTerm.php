<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperPaymentTerm
 */
final class PaymentTerm extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::PaymentTerms->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'rate_lines',
        'is_active',
    ];

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:' . ERPTables::Companies->value . ',id'],
            'name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string'],
            'rate_lines' => ['required', 'json'],
            'is_active' => ['boolean'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:128'],
            'description' => ['nullable', 'string'],
            'rate_lines' => ['sometimes', 'json'],
            'is_active' => ['boolean'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'rate_lines' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
