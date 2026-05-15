<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\TaxKind;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Exceptions\TaxCodeImmutableAttributeException;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * Immutable fiscal code row (VAT / withholding). Rate changes = new row + supersession link.
 *
 * @mixin \Eloquent
 * @mixin IdeHelperTaxCode
 */
final class TaxCode extends Model
{
    use BelongsToCompany;

    #[Override]
    protected $table = ERPTables::TaxCodes->value;

    private VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    #[\Override]
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
            'company_id' => ['required', 'integer', 'exists:'.ERPTables::Companies->value.',id'],
            'code' => ['required', 'string', 'max:64'],
            'kind' => ['required', 'string', TaxKind::validationRule()],
            'country' => ['required', 'string', 'size:2'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'label' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'effective_from' => ['required', 'date'],
            'replaced_by_tax_code_id' => ['nullable', 'integer', 'exists:'.ERPTables::TaxCodes->value.',id'],
            'meta' => ['nullable', 'array'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'is_active' => ['sometimes', 'boolean'],
            'replaced_by_tax_code_id' => ['nullable', 'integer', 'exists:'.ERPTables::TaxCodes->value.',id'],
            'meta' => ['nullable', 'array'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::updating(function (TaxCode $code): void {
            foreach (['code', 'kind', 'country', 'rate', 'label', 'effective_from', 'company_id', 'meta'] as $locked) {
                if ($code->isDirty($locked)) {
                    throw TaxCodeImmutableAttributeException::make($locked);
                }
            }
        });
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
