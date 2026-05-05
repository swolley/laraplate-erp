<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Observers\InvoiceObserver;
use Modules\Core\Overrides\Model;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * Minimal commercial invoice header (M2/M3 bridge): full lifecycle comes in M3.5.
 *
 * @mixin IdeHelperInvoice
 */
#[ObservedBy([InvoiceObserver::class])]
class Invoice extends Model
{
    use BelongsToCompany;

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    protected $fillable = [
        'company_id',
        'direction',
        'currency',
        'posted_at',
        'journal_entry_id',
        'notes',
    ];

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journal_entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * @return HasMany<EInvoiceSubmission, $this>
     */
    public function eInvoiceSubmissions(): HasMany
    {
        return $this->hasMany(EInvoiceSubmission::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'direction' => ['required', 'string', InvoiceDirection::validationRule()],
            'currency' => ['required', 'string', 'size:3'],
            'posted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'direction' => ['sometimes', 'string', InvoiceDirection::validationRule()],
            'currency' => ['sometimes', 'string', 'size:3'],
            'posted_at' => ['nullable', 'date'],
            'journal_entry_id' => ['nullable', 'integer', 'exists:journal_entries,id'],
            'notes' => ['nullable', 'string'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'direction' => InvoiceDirection::class,
            'posted_at' => 'immutable_datetime',
        ];
    }

    protected function shouldVersioning(): bool
    {
        return false;
    }
}
