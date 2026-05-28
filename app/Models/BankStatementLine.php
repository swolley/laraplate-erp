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
