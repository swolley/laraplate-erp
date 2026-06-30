<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;

uses(RefreshDatabase::class);

it('defines fiscal periods relationship', function (): void {
    $relation = (new FiscalYear)->fiscal_periods();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(FiscalPeriod::class);
});

it('loads fiscal periods for a fiscal year', function (): void {
    $company = Company::query()->create([
        'slug' => 'fy-' . uniqid(),
        'name' => 'FY Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $fiscal_year = FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    FiscalPeriod::query()->create([
        'fiscal_year_id' => $fiscal_year->id,
        'period_no' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ]);

    expect($fiscal_year->fiscal_periods)->toHaveCount(1);
});
