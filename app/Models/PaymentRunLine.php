<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\PaymentRunLineStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * Immutable beneficiary/payment snapshot inside a supplier payment run.
 *
 * @property int|string $id
 * @property int $company_id
 * @property int $payment_run_id
 * @property int $payment_schedule_line_id
 * @property int $party_id
 * @property int|null $party_bank_account_id
 * @property numeric-string $amount_doc
 * @property string $currency_doc
 * @property numeric-string $amount_local
 * @property string $currency_local
 * @property \Carbon\CarbonInterface $due_date
 * @property string $beneficiary_name
 * @property string $beneficiary_iban
 * @property string|null $beneficiary_bic
 * @property string|null $remittance_information
 * @property PaymentRunLineStatus $status
 * @mixin \Eloquent
 * @mixin IdeHelperPaymentRunLine
 */
final class PaymentRunLine extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::PaymentRunLines->value;

    #[Override]
    protected $fillable = [
        'company_id',
        'payment_run_id',
        'payment_schedule_line_id',
        'party_id',
        'party_bank_account_id',
        'amount_doc',
        'currency_doc',
        'amount_local',
        'currency_local',
        'due_date',
        'beneficiary_name',
        'beneficiary_iban',
        'beneficiary_bic',
        'remittance_information',
        'status',
    ];

    /**
     * @return BelongsTo<PaymentRun, $this>
     */
    public function payment_run(): BelongsTo
    {
        return $this->belongsTo(PaymentRun::class);
    }

    /**
     * @return BelongsTo<PaymentScheduleLine, $this>
     */
    public function schedule_line(): BelongsTo
    {
        return $this->belongsTo(PaymentScheduleLine::class, 'payment_schedule_line_id');
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<PartyBankAccount, $this>
     */
    public function party_bank_account(): BelongsTo
    {
        return $this->belongsTo(PartyBankAccount::class);
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
            'payment_run_id' => ['required', 'integer', 'exists:' . ERPTables::PaymentRuns->value . ',id'],
            'payment_schedule_line_id' => ['required', 'integer', 'exists:' . ERPTables::PaymentScheduleLines->value . ',id'],
            'party_id' => ['required', 'integer', 'exists:' . ERPTables::Parties->value . ',id'],
            'party_bank_account_id' => ['nullable', 'integer', 'exists:' . ERPTables::PartyBankAccounts->value . ',id'],
            'amount_doc' => ['required', 'numeric', 'min:0.0001'],
            'currency_doc' => ['required', 'string', 'size:3'],
            'amount_local' => ['required', 'numeric', 'min:0.0001'],
            'currency_local' => ['required', 'string', 'size:3'],
            'due_date' => ['required', 'date'],
            'beneficiary_name' => ['required', 'string', 'max:255'],
            'beneficiary_iban' => ['required', 'string', 'max:34'],
            'beneficiary_bic' => ['nullable', 'string', 'max:11'],
            'remittance_information' => ['nullable', 'string', 'max:140'],
            'status' => ['string', PaymentRunLineStatus::validationRule()],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'status' => ['string', PaymentRunLineStatus::validationRule()],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'amount_doc' => 'decimal:4',
            'amount_local' => 'decimal:4',
            'due_date' => 'date',
            'status' => PaymentRunLineStatus::class,
        ];
    }
}
