<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\VatSettlementStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\VatSettlement;
use Modules\ERP\Services\Accounting\VatSettlementService;

uses(RefreshDatabase::class);

function createVatSettlementFixture(): array
{
    $company = Company::query()->create([
        'slug' => 'vat-settle-' . uniqid(),
        'name' => 'VAT Settlement Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $fiscal_year = FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    $fiscal_period = FiscalPeriod::query()->create([
        'fiscal_year_id' => $fiscal_year->id,
        'period_no' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ]);

    return [$company, $fiscal_period];
}

it('confirms a draft vat settlement', function (): void {
    [$company, $fiscal_period] = createVatSettlementFixture();
    $settlement = VatSettlement::query()->create([
        'company_id' => $company->id,
        'fiscal_period_id' => $fiscal_period->id,
        'vat_sales' => '100.0000',
        'vat_purchases' => '40.0000',
        'previous_credit' => '0.0000',
        'settlement_amount' => '60.0000',
        'status' => VatSettlementStatus::Draft,
    ]);

    app(VatSettlementService::class)->confirm($settlement, 7);

    $settlement->refresh();

    expect($settlement->status)->toBe(VatSettlementStatus::Confirmed)
        ->and($settlement->confirmed_by)->toBe(7)
        ->and($settlement->confirmed_at)->not->toBeNull();
});

it('rejects confirming an already confirmed vat settlement', function (): void {
    [$company, $fiscal_period] = createVatSettlementFixture();
    $settlement = VatSettlement::query()->create([
        'company_id' => $company->id,
        'fiscal_period_id' => $fiscal_period->id,
        'vat_sales' => '0.0000',
        'vat_purchases' => '0.0000',
        'previous_credit' => '0.0000',
        'settlement_amount' => '0.0000',
        'status' => VatSettlementStatus::Confirmed,
        'confirmed_at' => now(),
        'confirmed_by' => 1,
    ]);

    expect(fn () => app(VatSettlementService::class)->confirm($settlement, 2))
        ->toThrow(ValidationException::class);
});
