<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
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
        'invoice_type',
        'credited_invoice_id',
        'reference',
        'currency',
        'posted_at',
        'journal_entry_id',
        'notes',
    ];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function credited_invoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credited_invoice_id');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function credit_notes(): HasMany
    {
        return $this->hasMany(self::class, 'credited_invoice_id');
    }

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
            'invoice_type' => ['required', 'string', InvoiceType::validationRule()],
            'credited_invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'currency' => ['required', 'string', 'size:3'],
            'reference' => ['nullable', 'string', 'max:64'],
            'posted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'direction' => ['sometimes', 'string', InvoiceDirection::validationRule()],
            'invoice_type' => ['sometimes', 'string', InvoiceType::validationRule()],
            'credited_invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'reference' => ['nullable', 'string', 'max:64'],
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
            'invoice_type' => InvoiceType::class,
            'posted_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (Invoice $invoice): void {
            if ($invoice->invoice_type !== InvoiceType::Invoice && $invoice->credited_invoice_id !== null) {
                $original = self::query()->withoutGlobalScopes()->find((int) $invoice->credited_invoice_id);
                if ($original === null) {
                    throw ValidationException::withMessages([
                        'credited_invoice_id' => ['The credited invoice does not exist.'],
                    ]);
                }
                if ((int) $original->company_id !== (int) $invoice->company_id) {
                    throw ValidationException::withMessages([
                        'credited_invoice_id' => ['The credited invoice must belong to the same company.'],
                    ]);
                }
            }
        });
    }

    protected function shouldVersioning(): bool
    {
        return false;
    }
}
