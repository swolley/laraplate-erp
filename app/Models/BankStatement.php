<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @property int|string $id
 * @property int $company_id
 * @property int $bank_account_id
 * @property \Carbon\CarbonInterface|null $period_start
 * @property \Carbon\CarbonInterface|null $period_end
 * @property \Carbon\CarbonInterface|null $imported_at
 * @property string|null $source_filename
 * @property-read BankAccount|null $bank_account
 *
 * @mixin \Eloquent
 * @mixin IdeHelperBankStatement
 */
final class BankStatement extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::BankStatements->value;

    #[Override]
    protected $fillable = [
        'company_id',
        'bank_account_id',
        'period_start',
        'period_end',
        'imported_at',
        'source_filename',
    ];

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bank_account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return HasMany<BankStatementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'imported_at' => 'immutable_datetime',
        ];
    }
}
