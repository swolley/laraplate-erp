<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * Supplier or customer bank account used for payment execution snapshots.
 *
 * @property int|string $id
 * @property int $company_id
 * @property int $party_id
 * @property string $beneficiary_name
 * @property string $iban
 * @property string|null $bic
 * @property string $currency
 * @property bool $is_default
 * @property bool $is_active
 * @mixin \Eloquent
 * @mixin IdeHelperPartyBankAccount
 */
final class PartyBankAccount extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::PartyBankAccounts->value;

    #[Override]
    protected $fillable = [
        'company_id',
        'party_id',
        'beneficiary_name',
        'iban',
        'bic',
        'currency',
        'is_default',
        'is_active',
    ];

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:' . ERPTables::Companies->value . ',id'],
            'party_id' => ['required', 'integer', 'exists:' . ERPTables::Parties->value . ',id'],
            'beneficiary_name' => ['required', 'string', 'max:255'],
            'iban' => ['required', 'string', 'max:34'],
            'bic' => ['nullable', 'string', 'max:11'],
            'currency' => ['required', 'string', 'size:3'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'beneficiary_name' => ['sometimes', 'string', 'max:255'],
            'iban' => ['sometimes', 'string', 'max:34'],
            'bic' => ['nullable', 'string', 'max:11'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::saving(static function (PartyBankAccount $bank_account): void {
            $bank_account->iban = strtoupper(str_replace(' ', '', (string) $bank_account->iban));
            $bank_account->bic = $bank_account->bic !== null
                ? strtoupper(str_replace(' ', '', (string) $bank_account->bic))
                : null;
            $bank_account->currency = strtoupper((string) $bank_account->currency);
        });
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
