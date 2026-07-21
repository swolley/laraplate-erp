<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\VatSettlement;

uses(RefreshDatabase::class);

it('discovers the ERP VAT settlement compute command', function (): void {
    expect(Artisan::all())->toHaveKey('erp:vat-settlements:compute');
});

it('previews one selected VAT period without persistence', function (): void {
    $company = Company::query()->create([
        'slug' => 'vat-command',
        'name' => 'VAT command',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $year = FiscalYear::query()->create([
        'company_id' => $company->getKey(),
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    FiscalPeriod::query()->create([
        'fiscal_year_id' => $year->getKey(),
        'period_no' => 3,
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
        'is_closed' => false,
    ]);

    $this->artisan('erp:vat-settlements:compute', [
        '--company' => $company->getKey(),
        '--year' => 2026,
        '--period' => '2026-3',
        '--dry-run' => true,
        '--format' => 'json',
    ])
        ->expectsOutputToContain('"previewed": 1')
        ->assertSuccessful();

    expect(VatSettlement::query()->withoutGlobalScopes()->where('company_id', $company->getKey())->count())->toBe(0);
});
