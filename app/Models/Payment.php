<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\PaymentDirection;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @property int|string $id
 * @property int $company_id
 * @property int $party_id
 * @property PaymentDirection $direction
 * @property \Carbon\CarbonInterface|null $payment_date
 * @property numeric-string $amount_doc
 * @property string $currency_doc
 * @property numeric-string $amount_local
 * @property string $currency_local
 * @property numeric-string $fx_rate
 * @property string|null $reference
 * @property int|null $bank_account_id
 * @property int|null $journal_entry_id
 * @property string|null $notes
 * @mixin \Eloquent
 * @mixin IdeHelperPayment
 */
final class Payment extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::Payments->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'party_id',
        'direction',
        'payment_date',
        'amount_doc',
        'currency_doc',
        'amount_local',
        'currency_local',
        'fx_rate',
        'reference',
        'bank_account_id',
        'journal_entry_id',
        'notes',
    ];

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journal_entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bank_account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * @return BelongsToMany<PaymentScheduleLine, $this>
     */
    public function schedule_lines(): BelongsToMany
    {
        return $this->belongsToMany(PaymentScheduleLine::class, 'payment_allocations')
            ->withPivot(['allocated_amount_doc', 'allocated_amount_local'])
            ->withTimestamps();
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
            'direction' => ['required', 'string', PaymentDirection::validationRule()],
            'payment_date' => ['required', 'date'],
            'amount_doc' => ['required', 'numeric', 'min:0.0001'],
            'currency_doc' => ['required', 'string', 'size:3'],
            'amount_local' => ['required', 'numeric', 'min:0.0001'],
            'currency_local' => ['required', 'string', 'size:3'],
            'fx_rate' => ['required', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:64'],
            'bank_account_id' => ['nullable', 'integer'],
            'journal_entry_id' => ['nullable', 'integer', 'exists:' . ERPTables::JournalEntries->value . ',id'],
            'notes' => ['nullable', 'string'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'party_id' => ['sometimes', 'integer', 'exists:' . ERPTables::Parties->value . ',id'],
            'direction' => ['sometimes', 'string', PaymentDirection::validationRule()],
            'payment_date' => ['sometimes', 'date'],
            'amount_doc' => ['sometimes', 'numeric', 'min:0.0001'],
            'currency_doc' => ['sometimes', 'string', 'size:3'],
            'amount_local' => ['sometimes', 'numeric', 'min:0.0001'],
            'currency_local' => ['sometimes', 'string', 'size:3'],
            'fx_rate' => ['sometimes', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:64'],
            'bank_account_id' => ['nullable', 'integer'],
            'journal_entry_id' => ['nullable', 'integer', 'exists:' . ERPTables::JournalEntries->value . ',id'],
            'notes' => ['nullable', 'string'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'direction' => PaymentDirection::class,
            'payment_date' => 'date',
            'amount_doc' => 'decimal:4',
            'amount_local' => 'decimal:4',
            'fx_rate' => 'decimal:8',
        ];
    }
}
