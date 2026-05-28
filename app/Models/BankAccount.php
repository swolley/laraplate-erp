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
 * @mixin IdeHelperBankAccount
 */
final class BankAccount extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::BankAccounts->value;

    #[Override]
    protected $fillable = [
        'company_id',
        'name',
        'iban',
        'account_no',
        'currency',
        'is_active',
    ];

    /**
     * @return HasMany<BankStatement, $this>
     */
    public function statements(): HasMany
    {
        return $this->hasMany(BankStatement::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:' . ERPTables::Companies->value . ',id'],
            'name' => ['required', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34'],
            'account_no' => ['nullable', 'string', 'max:64'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34'],
            'account_no' => ['nullable', 'string', 'max:64'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
