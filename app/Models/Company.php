<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * Tenant root for the Business / ERP domain.
 *
 * Holds fiscal identity (tax_id, fiscal_country) and the functional currency
 * used as `amount_local` for double-entry journal balancing.
 *
 * @property int|string $id
 * @property string $default_currency
 * @property bool $is_default
 * @property array<string, mixed>|null $settings
 * @mixin \Eloquent
 * @mixin IdeHelperCompany
 */
final class Company extends Model
{
    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::Companies->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'slug',
        'name',
        'legal_name',
        'tax_id',
        'fiscal_country',
        'fiscal_regime',
        'legal_address_line',
        'legal_postal_code',
        'legal_city',
        'legal_province',
        'legal_country',
        'rea_office',
        'rea_number',
        'share_capital',
        'sole_shareholder',
        'liquidation_status',
        'default_currency',
        'settings',
        'is_default',
    ];

    /**
     * Resolve the default company (used as bootstrap fallback when no tenant
     * context is active, e.g. CLI seeders or backfill jobs).
     */
    public static function getDefault(): ?self
    {
        return self::query()->withoutGlobalScopes()
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

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'slug' => ['required', 'string', 'max:64', 'unique:' . ERPTables::Companies->value . ',slug'],
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'fiscal_country' => ['required', 'string', 'size:2'],
            'fiscal_regime' => ['nullable', 'string', 'max:4'],
            'legal_address_line' => ['nullable', 'string', 'max:255'],
            'legal_postal_code' => ['nullable', 'string', 'max:16'],
            'legal_city' => ['nullable', 'string', 'max:128'],
            'legal_province' => ['nullable', 'string', 'max:8'],
            'legal_country' => ['nullable', 'string', 'size:2'],
            'rea_office' => ['nullable', 'string', 'max:8'],
            'rea_number' => ['nullable', 'string', 'max:32'],
            'share_capital' => ['nullable', 'numeric', 'min:0'],
            'sole_shareholder' => ['nullable', 'boolean'],
            'liquidation_status' => ['nullable', 'string', 'max:2'],
            'default_currency' => ['required', 'string', 'size:3'],
            'settings' => ['nullable', 'array'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'slug' => ['sometimes', 'string', 'max:64', 'unique:' . ERPTables::Companies->value . ',slug,' . $this->companySlugUniqueId()],
            'name' => ['sometimes', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'fiscal_country' => ['sometimes', 'string', 'size:2'],
            'fiscal_regime' => ['nullable', 'string', 'max:4'],
            'legal_address_line' => ['nullable', 'string', 'max:255'],
            'legal_postal_code' => ['nullable', 'string', 'max:16'],
            'legal_city' => ['nullable', 'string', 'max:128'],
            'legal_province' => ['nullable', 'string', 'max:8'],
            'legal_country' => ['nullable', 'string', 'size:2'],
            'rea_office' => ['nullable', 'string', 'max:8'],
            'rea_number' => ['nullable', 'string', 'max:32'],
            'share_capital' => ['nullable', 'numeric', 'min:0'],
            'sole_shareholder' => ['nullable', 'boolean'],
            'liquidation_status' => ['nullable', 'string', 'max:2'],
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
    protected static function booted(): void
    {
        self::saving(function (Company $company): void {
            if (! $company->is_default) {
                return;
            }

            self::query()->withoutGlobalScopes()
                ->where('id', '!=', $company->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_default' => 'boolean',
            'share_capital' => 'decimal:2',
            'sole_shareholder' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function default(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    private function companySlugUniqueId(): int|string
    {
        $id = $this->getAttribute('id');

        if (is_int($id) || is_string($id)) {
            return $id;
        }

        return 0;
    }
}
