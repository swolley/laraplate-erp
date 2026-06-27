<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Exceptions\FiscalPeriodAlreadyClosedException;
use Modules\ERP\Exceptions\FiscalYearAlreadyClosedException;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Services\Accounting\FiscalPeriodCloser;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    FiscalPeriod::disableVersioning();
    FiscalYear::disableVersioning();
});

afterEach(function (): void {
    FiscalPeriod::enableVersioning();
    FiscalYear::enableVersioning();
});

/**
 * @return array{0: Company, 1: FiscalYear, 2: FiscalPeriod}
 */
function fiscalPeriodCloserFixtures(): array
{
    $company = Company::query()->create([
        'slug' => 'fp-closer-' . uniqid(),
        'name' => 'Fiscal Period Closer Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $fiscal_year = FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_closed' => false,
    ]);

    $period = FiscalPeriod::query()->create([
        'fiscal_year_id' => $fiscal_year->id,
        'period_no' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'is_closed' => false,
    ]);

    return [$company, $fiscal_year, $period];
}

it('closes an open fiscal period and persists', function (): void {
    [, , $period] = fiscalPeriodCloserFixtures();

    (new FiscalPeriodCloser())->closePeriod($period);

    $period->refresh();

    expect($period->is_closed)->toBeTrue();
});

it('throws when closing a period already marked closed', function (): void {
    [, , $period] = fiscalPeriodCloserFixtures();
    $period->is_closed = true;
    $period->setSkipValidation(true);
    $period->save();

    expect(fn () => (new FiscalPeriodCloser())->closePeriod($period))
        ->toThrow(FiscalPeriodAlreadyClosedException::class);
});

it('throws when closing a year already marked closed', function (): void {
    [, $fiscal_year] = fiscalPeriodCloserFixtures();
    $fiscal_year->is_closed = true;
    $fiscal_year->setSkipValidation(true);
    $fiscal_year->save();

    expect(fn () => (new FiscalPeriodCloser())->closeYear($fiscal_year))
        ->toThrow(FiscalYearAlreadyClosedException::class);
});
