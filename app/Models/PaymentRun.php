<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\PaymentRunFormat;
use Modules\ERP\Casts\PaymentRunStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * Approved batch of outbound supplier payments exported as a bank file.
 *
 * @property int|string $id
 * @property int $company_id
 * @property int $bank_account_id
 * @property \Carbon\CarbonInterface $execution_date
 * @property string $currency
 * @property numeric-string $total_amount_doc
 * @property numeric-string $total_amount_local
 * @property PaymentRunStatus $status
 * @property PaymentRunFormat $format
 * @property \Carbon\CarbonInterface|null $approved_at
 * @property \Carbon\CarbonInterface|null $exported_at
 * @property string|null $export_file_name
 * @property string|null $export_checksum
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PaymentRunLine> $lines
 * @mixin \Eloquent
 * @mixin IdeHelperPaymentRun
 */
final class PaymentRun extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::PaymentRuns->value;

    #[Override]
    protected $fillable = [
        'company_id',
        'bank_account_id',
        'execution_date',
        'currency',
        'total_amount_doc',
        'total_amount_local',
        'status',
        'format',
        'approved_at',
        'exported_at',
        'export_file_name',
        'export_checksum',
    ];

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bank_account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return HasMany<PaymentRunLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PaymentRunLine::class);
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
            'bank_account_id' => ['required', 'integer', 'exists:' . ERPTables::BankAccounts->value . ',id'],
            'execution_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3'],
            'total_amount_doc' => ['numeric', 'min:0'],
            'total_amount_local' => ['numeric', 'min:0'],
            'status' => ['string', PaymentRunStatus::validationRule()],
            'format' => ['string', PaymentRunFormat::validationRule()],
            'approved_at' => ['nullable', 'date'],
            'exported_at' => ['nullable', 'date'],
            'export_file_name' => ['nullable', 'string', 'max:255'],
            'export_checksum' => ['nullable', 'string', 'max:128'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'bank_account_id' => ['sometimes', 'integer', 'exists:' . ERPTables::BankAccounts->value . ',id'],
            'execution_date' => ['sometimes', 'date'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'total_amount_doc' => ['numeric', 'min:0'],
            'total_amount_local' => ['numeric', 'min:0'],
            'status' => ['string', PaymentRunStatus::validationRule()],
            'format' => ['string', PaymentRunFormat::validationRule()],
            'approved_at' => ['nullable', 'date'],
            'exported_at' => ['nullable', 'date'],
            'export_file_name' => ['nullable', 'string', 'max:255'],
            'export_checksum' => ['nullable', 'string', 'max:128'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::saving(static function (PaymentRun $payment_run): void {
            if ($payment_run->exists && $payment_run->getOriginal('status') === PaymentRunStatus::Exported->value) {
                throw ValidationException::withMessages([
                    'status' => ['An exported payment run cannot be modified.'],
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'execution_date' => 'date',
            'total_amount_doc' => 'decimal:4',
            'total_amount_local' => 'decimal:4',
            'status' => PaymentRunStatus::class,
            'format' => PaymentRunFormat::class,
            'approved_at' => 'immutable_datetime',
            'exported_at' => 'immutable_datetime',
        ];
    }
}
