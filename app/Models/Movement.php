<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\MovementType;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

// use Modules\ERP\Database\Factories\MovementFactory;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperMovement
 */
final class Movement extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::Movements->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'type',
        'occurred_on',
        'amount_doc',
        'currency_doc',
        'amount_local',
        'currency_local',
        'fx_rate',
        'counterparty_account_id',
        'posted_journal_entry_id',
        'description',
    ];

    /** @return BelongsTo<Account, $this> */
    public function counterparty_account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'counterparty_account_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function posted_journal_entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'posted_journal_entry_id');
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $attributes = [
            'company_id' => ['required', 'integer', 'exists:' . ERPTables::Companies->value . ',id'],
            'type' => ['required', 'string', MovementType::validationRule()],
            'occurred_on' => ['required', 'date'],
            'amount_doc' => ['required', 'numeric', 'gt:0'],
            'currency_doc' => ['required', 'string', 'size:3'],
            'amount_local' => ['nullable', 'numeric', 'gte:0'],
            'currency_local' => ['nullable', 'string', 'size:3'],
            'fx_rate' => ['nullable', 'numeric', 'gt:0'],
            'counterparty_account_id' => ['required', 'integer', 'exists:' . ERPTables::Accounts->value . ',id'],
            'posted_journal_entry_id' => ['nullable', 'integer', 'exists:' . ERPTables::JournalEntries->value . ',id'],
            'description' => ['nullable', 'string'],
        ];

        $rules['create'] = array_merge($rules['create'], $attributes);
        $rules['update'] = array_merge($rules['update'], $attributes);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'occurred_on' => 'immutable_date',
        ];
    }

    // protected static function newFactory(): MovementFactory
    // {
    //     // return MovementFactory::new();
    // }
}
