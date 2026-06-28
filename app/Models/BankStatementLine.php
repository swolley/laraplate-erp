<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\BankStatementLineStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @property int|string $id
 * @property int $company_id
 * @property int $bank_statement_id
 * @property int|null $matched_payment_id
 * @property \Carbon\CarbonInterface|null $booked_at
 * @property \Carbon\CarbonInterface|null $value_at
 * @property string|null $reference
 * @property string|null $description
 * @property numeric-string $amount_doc
 * @property string $currency_doc
 * @property numeric-string $amount_local
 * @property string $currency_local
 * @property numeric-string $fx_rate
 * @property BankStatementLineStatus $status
 * @property array<string, mixed>|null $raw_payload
 * @property-read BankStatement|null $bank_statement
 * @property-read Payment|null $matched_payment
 *
 * @mixin \Eloquent
 * @mixin IdeHelperBankStatementLine
 */
final class BankStatementLine extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::BankStatementLines->value;

    #[Override]
    protected $fillable = [
        'company_id',
        'bank_statement_id',
        'matched_payment_id',
        'booked_at',
        'value_at',
        'reference',
        'description',
        'amount_doc',
        'currency_doc',
        'amount_local',
        'currency_local',
        'fx_rate',
        'status',
        'raw_payload',
    ];

    /**
     * @return BelongsTo<BankStatement, $this>
     */
    public function bank_statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function matched_payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'matched_payment_id');
    }

    /**
     * @return array<string, mixed>
     */

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'raw_payload' => ['nullable', 'json'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'raw_payload' => ['nullable', 'json'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'booked_at' => 'date',
            'value_at' => 'date',
            'amount_doc' => 'decimal:4',
            'amount_local' => 'decimal:4',
            'fx_rate' => 'decimal:8',
            'status' => BankStatementLineStatus::class,
            'raw_payload' => 'array',
        ];
    }
}
