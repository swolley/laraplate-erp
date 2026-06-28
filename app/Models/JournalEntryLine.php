<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Exceptions\PostedJournalImmutableException;
use Override;

/**
 * Single line in a journal entry (Dare/Avere as signed amount_local).
 *
 * @property int|string $id
 * @property int $journal_entry_id
 * @property int $line_no
 * @property int $account_id
 * @property int|null $tax_code_id
 * @property numeric-string $amount_doc
 * @property string $currency_doc
 * @property numeric-string $amount_local
 * @property string $currency_local
 * @property numeric-string $fx_rate
 * @property string|null $tax_code
 * @property numeric-string|null $tax_rate
 * @property string|null $tax_label
 * @property string|null $description
 *
 * @mixin \Eloquent
 * @mixin IdeHelperJournalEntryLine
 */
final class JournalEntryLine extends Model
{
    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::JournalEntryLines->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'journal_entry_id',
        'line_no',
        'account_id',
        'tax_code_id',
        'amount_doc',
        'currency_doc',
        'amount_local',
        'currency_local',
        'fx_rate',
        'tax_code',
        'tax_rate',
        'tax_label',
        'description',
    ];

    /**
     * @return BelongsTo<TaxCode, $this>
     */
    public function source_tax_code(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'tax_code_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journal_entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return array<string, mixed>
     */

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'journal_entry_id' => ['required', 'integer', 'exists:' . ERPTables::JournalEntries->value . ',id'],
            'line_no' => ['required', 'integer', 'min:1', 'max:65535'],
            'account_id' => ['required', 'integer', 'exists:' . ERPTables::Accounts->value . ',id'],
            'tax_code_id' => ['nullable', 'integer', 'exists:' . ERPTables::TaxCodes->value . ',id'],
            'amount_doc' => ['required', 'numeric'],
            'currency_doc' => ['required', 'string', 'size:3'],
            'amount_local' => ['required', 'numeric'],
            'currency_local' => ['required', 'string', 'size:3'],
            'fx_rate' => ['required', 'numeric'],
            'tax_code' => ['nullable', 'string', 'max:32'],
            'tax_rate' => ['nullable', 'numeric'],
            'tax_label' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'line_no' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'account_id' => ['sometimes', 'integer', 'exists:' . ERPTables::Accounts->value . ',id'],
            'tax_code_id' => ['nullable', 'integer', 'exists:' . ERPTables::TaxCodes->value . ',id'],
            'amount_doc' => ['sometimes', 'numeric'],
            'currency_doc' => ['sometimes', 'string', 'size:3'],
            'amount_local' => ['sometimes', 'numeric'],
            'currency_local' => ['sometimes', 'string', 'size:3'],
            'fx_rate' => ['sometimes', 'numeric'],
            'tax_code' => ['nullable', 'string', 'max:32'],
            'tax_rate' => ['nullable', 'numeric'],
            'tax_label' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::updating(function (JournalEntryLine $line): void {
            if (self::journalHeaderIsPosted($line)) {
                throw PostedJournalImmutableException::make();
            }
        });

        self::deleting(function (JournalEntryLine $line): void {
            if (self::journalHeaderIsPosted($line)) {
                throw PostedJournalImmutableException::make();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
        ];
    }

    private static function journalHeaderIsPosted(self $line): bool
    {
        $journal_entry_id = $line->journal_entry_id;

        if ($journal_entry_id === 0) {
            return false;
        }

        $posted_at = JournalEntry::query()->withoutGlobalScopes()
            ->whereKey($journal_entry_id)
            ->value('posted_at');

        return $posted_at !== null;
    }
}
