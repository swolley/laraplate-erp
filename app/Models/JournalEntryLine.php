<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * Single line in a journal entry (Dare/Avere as signed amount_local).
 *
 * @mixin IdeHelperJournalEntryLine
 */
class JournalEntryLine extends Model
{
    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    protected $fillable = [
        'journal_entry_id',
        'line_no',
        'account_id',
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

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'journal_entry_id' => ['required', 'integer', 'exists:journal_entries,id'],
            'line_no' => ['required', 'integer', 'min:1', 'max:65535'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
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
            'account_id' => ['sometimes', 'integer', 'exists:accounts,id'],
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

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
        ];
    }
}
