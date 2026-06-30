<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Services\Accounting\FiscalCalendarInstaller;

uses(RefreshDatabase::class);

it('installs a calendar year with twelve monthly periods', function (): void {
    $company = Company::query()->create([
        'slug' => 'fiscal-install-' . uniqid(),
        'name' => 'Fiscal Install Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $year = 2026;

    $fiscal_year = app(FiscalCalendarInstaller::class)->ensureCalendarYear($company, $year);

    expect($fiscal_year->year)->toBe($year)
        ->and($fiscal_year->start_date->toDateString())->toBe('2026-01-01')
        ->and($fiscal_year->end_date->toDateString())->toBe('2026-12-31')
        ->and(FiscalPeriod::query()->where('fiscal_year_id', $fiscal_year->id)->count())->toBe(12);
});

it('returns an existing fiscal year without duplicating periods', function (): void {
    $company = Company::query()->create([
        'slug' => 'fiscal-existing-' . uniqid(),
        'name' => 'Fiscal Existing Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $installer = app(FiscalCalendarInstaller::class);
    $year = 2027;

    $first = $installer->ensureCalendarYear($company, $year);
    $period_count = FiscalPeriod::query()->where('fiscal_year_id', $first->id)->count();

    $second = $installer->ensureCalendarYear($company, $year);

    expect($second->id)->toBe($first->id)
        ->and(FiscalYear::query()->where('company_id', $company->id)->where('year', $year)->count())->toBe(1)
        ->and(FiscalPeriod::query()->where('fiscal_year_id', $first->id)->count())->toBe($period_count);
});
