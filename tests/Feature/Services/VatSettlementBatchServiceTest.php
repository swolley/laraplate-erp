<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\VatSettlementStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\VatSettlement;
use Modules\ERP\Services\Accounting\VatSettlementBatchService;
use Modules\ERP\Services\Accounting\VatSettlementService;

uses(RefreshDatabase::class);

function vatBatchFixture(): array
{
    $company = Company::query()->create([
        'slug' => 'vat-batch-' . uniqid(),
        'name' => 'VAT batch',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $year = FiscalYear::query()->create([
        'company_id' => $company->getKey(),
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    $period_one = FiscalPeriod::query()->create([
        'fiscal_year_id' => $year->getKey(),
        'period_no' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'is_closed' => false,
    ]);
    $period_two = FiscalPeriod::query()->create([
        'fiscal_year_id' => $year->getKey(),
        'period_no' => 2,
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-28',
        'is_closed' => false,
    ]);

    return [$company, $period_one, $period_two];
}

it('previews open periods without persisting settlements', function (): void {
    [$company] = vatBatchFixture();

    $result = app(VatSettlementBatchService::class)->compute((int) $company->getKey(), 2026, dry_run: true);

    expect($result['summary'])->toMatchArray(['previewed' => 2, 'computed' => 0, 'failed' => 0])
        ->and(VatSettlement::query()->withoutGlobalScopes()->where('company_id', $company->getKey())->count())->toBe(0);
});

it('persists drafts and skips confirmed settlements', function (): void {
    [$company, $period_one, $period_two] = vatBatchFixture();
    VatSettlement::query()->create([
        'company_id' => $company->getKey(),
        'fiscal_period_id' => $period_one->getKey(),
        'status' => VatSettlementStatus::Confirmed,
        'confirmed_at' => now(),
        'confirmed_by' => 1,
    ]);

    $result = app(VatSettlementBatchService::class)->compute((int) $company->getKey(), 2026);

    expect($result['summary'])->toMatchArray(['computed' => 1, 'skipped' => 1, 'failed' => 0])
        ->and(VatSettlement::query()->withoutGlobalScopes()->where('fiscal_period_id', $period_one->getKey())->firstOrFail()->status)->toBe(VatSettlementStatus::Confirmed)
        ->and(VatSettlement::query()->withoutGlobalScopes()->where('fiscal_period_id', $period_two->getKey())->firstOrFail()->status)->toBe(VatSettlementStatus::Draft);
});

it('shares the same calculation between preview and persisted compute', function (): void {
    [$company, $period_one] = vatBatchFixture();
    $service = app(VatSettlementService::class);
    $preview = $service->preview((int) $company->getKey(), (int) $period_one->getKey());
    $settlement = $service->compute((int) $company->getKey(), (int) $period_one->getKey());

    expect($preview)->toMatchArray([
        'vat_sales' => (string) $settlement->vat_sales,
        'vat_purchases' => (string) $settlement->vat_purchases,
        'previous_credit' => (string) $settlement->previous_credit,
        'settlement_amount' => (string) $settlement->settlement_amount,
    ]);
});
