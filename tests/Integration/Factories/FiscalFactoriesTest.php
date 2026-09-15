<?php

declare(strict_types=1);

/**
 * A fiscal year without its periods is useless to a test: posting, closing and reporting all need
 * a period to land in. `withPeriods()` therefore has to produce a contiguous calendar — no gap a
 * document could fall through, no overlap that would make two periods claim the same date.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;

uses(RefreshDatabase::class);

it('creates a fiscal year, a period and an account', function (): void {
    expect(FiscalYear::factory()->create()->exists)->toBeTrue()
        ->and(FiscalPeriod::factory()->create()->exists)->toBeTrue()
        ->and(Account::factory()->create()->exists)->toBeTrue();
});

it('builds a year whose periods are contiguous and complete', function (): void {
    $year = FiscalYear::factory()->withPeriods()->create();

    $periods = $year->fiscal_periods()->orderBy('period_no')->get();

    expect($periods)->toHaveCount(12)
        ->and($periods->first()->start_date->toDateString())->toBe($year->start_date->toDateString())
        ->and($periods->last()->end_date->toDateString())->toBe($year->end_date->toDateString());

    $periods->sliding(2)->each(function ($pair): void {
        [$current, $next] = [$pair->first(), $pair->last()];

        expect($current->end_date->addDay()->toDateString())->toBe($next->start_date->toDateString());
    });
});

it('opens periods by default and closes them on request', function (): void {
    expect(FiscalPeriod::factory()->create()->is_closed)->toBeFalse()
        ->and(FiscalPeriod::factory()->closed()->create()->is_closed)->toBeTrue();
});

it('keeps a year and its periods inside one company', function (): void {
    $company = Company::factory()->create();

    $year = FiscalYear::factory()->for($company)->withPeriods(3)->create();

    expect(Company::query()->count())->toBe(1)
        ->and($year->company_id)->toBe($company->id)
        ->and($year->fiscal_periods()->count())->toBe(3);
});

it('creates accounts of every kind the chart needs', function (): void {
    $company = Company::factory()->create();

    foreach (AccountKind::cases() as $kind) {
        expect(Account::factory()->for($company)->kind($kind)->create()->kind)->toBe($kind);
    }

    expect(Company::query()->count())->toBe(1);
});
