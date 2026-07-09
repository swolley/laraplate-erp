<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\VatSettlementStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\VatSettlement;

uses(RefreshDatabase::class);

it('defines fiscal period relationship', function (): void {
    expect((new VatSettlement)->fiscal_period())->toBeInstanceOf(BelongsTo::class);
});

it('rejects updates to confirmed settlements', function (): void {
    $company = Company::query()->create([
        'slug' => 'vat-settle-model-' . uniqid(),
        'name' => 'VAT Settlement Model Co',
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

    expect(fn () => $settlement->update(['vat_sales' => '10.0000']))
        ->toThrow(ValidationException::class, 'cannot be modified');
});

it('rejects deleting confirmed settlements', function (): void {
    $company = Company::query()->create([
        'slug' => 'vat-settle-del-' . uniqid(),
        'name' => 'VAT Settlement Delete Co',
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

    expect(fn () => $settlement->delete())
        ->toThrow(ValidationException::class, 'cannot be deleted');
});

it('allows deleting draft settlements', function (): void {
    $company = Company::query()->create([
        'slug' => 'vat-settle-draft-' . uniqid(),
        'name' => 'VAT Settlement Draft Co',
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
    $settlement = VatSettlement::query()->create([
        'company_id' => $company->id,
        'fiscal_period_id' => $fiscal_period->id,
        'vat_sales' => '0.0000',
        'vat_purchases' => '0.0000',
        'previous_credit' => '0.0000',
        'settlement_amount' => '0.0000',
        'status' => VatSettlementStatus::Draft,
    ]);

    $settlement->delete();

    expect(VatSettlement::query()->find($settlement->id))->toBeNull();
});

it('allows saving a confirmed settlement when no attributes changed', function (): void {
    $company = Company::query()->create([
        'slug' => 'vat-settle-touch-' . uniqid(),
        'name' => 'VAT Settlement Touch Co',
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
    $settlement = VatSettlement::query()->create([
        'company_id' => $company->id,
        'fiscal_period_id' => $fiscal_period->id,
        'vat_sales' => '10.0000',
        'vat_purchases' => '0.0000',
        'previous_credit' => '0.0000',
        'settlement_amount' => '10.0000',
        'status' => VatSettlementStatus::Confirmed,
        'confirmed_at' => now(),
        'confirmed_by' => 1,
    ]);

    $settlement->save();

    expect((string) $settlement->fresh()->vat_sales)->toBe('10.0000');
});
