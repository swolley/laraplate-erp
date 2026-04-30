<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Override;

/**
 * Tenant root for the Business / ERP domain.
 *
 * Holds fiscal identity (tax_id, fiscal_country) and the functional currency
 * used as `amount_local` for double-entry journal balancing.
 *
 * @mixin IdeHelperCompany
 */
class Company extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'slug',
        'name',
        'legal_name',
        'tax_id',
        'fiscal_country',
        'default_currency',
        'settings',
        'is_default',
    ];

    /**
     * Resolve the default company (used as bootstrap fallback when no tenant
     * context is active, e.g. CLI seeders or backfill jobs).
     */
    public static function default(): ?self
    {
        return static::query()->withoutGlobalScopes()
            ->where('is_default', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * @return HasMany<FiscalYear, $this>
     */
    public function fiscal_years(): HasMany
    {
        return $this->hasMany(FiscalYear::class);
    }

    /**
     * @return HasMany<DocumentSequence, $this>
     */
    public function document_sequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }

    /**
     * @return HasMany<JournalEntry, $this>
     */
    public function journal_entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * @return HasMany<TaxCode, $this>
     */
    public function tax_codes(): HasMany
    {
        return $this->hasMany(TaxCode::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'slug' => ['required', 'string', 'max:64', 'unique:companies,slug'],
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'fiscal_country' => ['required', 'string', 'size:2'],
            'default_currency' => ['required', 'string', 'size:3'],
            'settings' => ['nullable', 'array'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'slug' => ['sometimes', 'string', 'max:64', 'unique:companies,slug,' . $this->getKey()],
            'name' => ['sometimes', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'fiscal_country' => ['sometimes', 'string', 'size:2'],
            'default_currency' => ['sometimes', 'string', 'size:3'],
            'settings' => ['nullable', 'array'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        return $rules;
    }

    /**
     * Enforce a single default company invariant: when a row is flipped to
     * `is_default = true`, every other row is reset to false.
     */
    #[Override]
    protected static function booted(): void
    {
        static::saving(function (Company $company): void {
            if (! $company->is_default) {
                return;
            }

            static::query()->withoutGlobalScopes()
                ->where('id', '!=', $company->getKey() ?? 0)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    protected function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}
