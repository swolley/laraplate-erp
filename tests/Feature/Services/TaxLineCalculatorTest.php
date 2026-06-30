<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\TaxCode;
use Modules\ERP\Services\Taxation\TaxLineCalculator;

uses(RefreshDatabase::class);

it('computes line tax decimal-exact with HALF_UP rounding', function (): void {
    $calculator = app(TaxLineCalculator::class);

    expect($calculator->lineTax('0.0500', '2.5'))->toBe('0.0013')
        ->and($calculator->lineTax('100', '22'))->toBe('22.0000')
        ->and($calculator->lineTax('8.7776', '22'))->toBe('1.9311');
});

it('matches computeVatFromNet tax for VAT codes', function (): void {
    $company = Company::query()->create([
        'slug' => 'tax-calc',
        'name' => 'Tax Calc',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $vat = TaxCode::query()->create([
        'company_id' => $company->id,
        'code' => 'VAT22',
        'kind' => 'vat',
        'country' => 'IT',
        'rate' => 22,
        'label' => 'IVA 22%',
        'is_active' => true,
        'effective_from' => now()->toDateString(),
    ]);

    $calculator = app(TaxLineCalculator::class);

    expect($calculator->lineTax('8.7776', (string) $vat->rate))
        ->toBe($calculator->computeVatFromNet($vat, '8.7776')['tax']);
});
