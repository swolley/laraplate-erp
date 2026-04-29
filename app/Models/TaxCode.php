<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\ERP\Casts\TaxKind;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Exceptions\TaxCodeImmutableAttributeException;
use Modules\Core\Overrides\Model;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * Immutable fiscal code row (VAT / withholding). Rate changes = new row + supersession link.
 *
 * @mixin IdeHelperTaxCode
 */
class TaxCode extends Model
{
    use BelongsToCompany;

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    protected $fillable = [
        'company_id',
        'code',
        'kind',
        'country',
        'rate',
        'label',
        'is_active',
        'effective_from',
        'replaced_by_tax_code_id',
        'meta',
    ];

    protected static function booted(): void
    {
        static::updating(function (TaxCode $code): void {
            foreach (['code', 'kind', 'country', 'rate', 'label', 'effective_from', 'company_id', 'meta'] as $locked) {
                if ($code->isDirty($locked)) {
                    throw TaxCodeImmutableAttributeException::make($locked);
                }
            }
        });
    }

    /**
     * @return BelongsTo<TaxCode, $this>
     */
    public function replaced_by(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_tax_code_id');
    }

    /**
     * @return HasMany<TaxCode, $this>
     */
    public function supersedes(): HasMany
    {
        return $this->hasMany(self::class, 'replaced_by_tax_code_id');
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'code' => ['required', 'string', 'max:64'],
            'kind' => ['required', 'string', TaxKind::validationRule()],
            'country' => ['required', 'string', 'size:2'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'label' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'effective_from' => ['required', 'date'],
            'replaced_by_tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
            'meta' => ['nullable', 'array'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'is_active' => ['sometimes', 'boolean'],
            'replaced_by_tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
            'meta' => ['nullable', 'array'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'kind' => TaxKind::class,
            'is_active' => 'boolean',
            'effective_from' => 'immutable_date',
            'rate' => 'decimal:4',
            'meta' => 'array',
        ];
    }
}
