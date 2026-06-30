<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Exceptions\TaxCodeImmutableAttributeException;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\TaxCode;

uses(RefreshDatabase::class);

it('defines replaced_by and supersedes relationships', function (): void {
    $tax_code = new TaxCode;

    expect($tax_code->replaced_by())->toBeInstanceOf(BelongsTo::class)
        ->and($tax_code->supersedes())->toBeInstanceOf(HasMany::class);
});

it('rejects updates to immutable tax code attributes', function (): void {
    $company = Company::query()->create([
        'slug' => 'tax-code-' . uniqid(),
        'name' => 'Tax Code Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $tax_code = TaxCode::query()->create([
        'company_id' => $company->id,
        'code' => 'VAT22',
        'kind' => 'vat',
        'country' => 'IT',
        'rate' => 22,
        'label' => 'IVA 22%',
        'is_active' => true,
        'effective_from' => '2026-01-01',
    ]);

    expect(fn () => $tax_code->update(['rate' => 10]))
        ->toThrow(TaxCodeImmutableAttributeException::class);
});
