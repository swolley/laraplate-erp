<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Contracts\CurrencyConverter;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\ExchangeRate;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PaymentScheduleLine;
use Modules\ERP\Services\Currency\DatabaseCurrencyConverter;
use Modules\ERP\Services\Currency\FxRevaluationService;

uses(RefreshDatabase::class);

function createFxCompany(): Company
{
    return Company::query()->create([
        'slug' => 'fx-' . uniqid(),
        'name' => 'FX Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
}

function createFxAccount(Company $company, string $code, AccountKind $kind): Account
{
    return Account::query()->create([
        'company_id' => $company->id,
        'code' => $code,
        'name' => 'Account ' . $code,
        'kind' => $kind,
        'is_active' => true,
    ]);
}

function createFxReceivableSchedule(Company $company): PaymentScheduleLine
{
    $customer = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'USD Customer',
        'is_customer' => true,
        'is_supplier' => false,
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $customer->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'USD',
        'reference' => 'USD-001',
        'posted_at' => null,
    ]);

    return PaymentScheduleLine::query()->create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'due_date' => '2026-08-31',
        'amount_doc' => '1000.0000',
        'currency_doc' => 'USD',
        'amount_local' => '900.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '0.90000000',
        'paid_amount_doc' => '0.0000',
        'paid_amount_local' => '0.0000',
        'status' => PaymentScheduleStatus::Open,
    ]);
}

it('converts currencies using latest historical database rate and inverse rates', function (): void {
    ExchangeRate::query()->create([
        'from_currency' => 'USD',
        'to_currency' => 'EUR',
        'rate' => '0.90000000',
        'rate_date' => '2026-07-01',
        'source' => 'manual',
    ]);
    ExchangeRate::query()->create([
        'from_currency' => 'USD',
        'to_currency' => 'EUR',
        'rate' => '0.92000000',
        'rate_date' => '2026-07-15',
        'source' => 'manual',
    ]);

    $converter = app(CurrencyConverter::class);

    expect($converter)->toBeInstanceOf(DatabaseCurrencyConverter::class)
        ->and($converter->convert('USD', 'EUR', '100.00', CarbonImmutable::parse('2026-07-20')))->toBe([
            'rate' => 0.92,
            'amount' => 92.0,
        ])
        ->and(round($converter->getRate('EUR', 'USD', CarbonImmutable::parse('2026-07-20')), 8))->toBe(1.08695652);
});

it('posts unrealized FX revaluation journals for open foreign receivables', function (): void {
    $company = createFxCompany();
    $receivables = createFxAccount($company, '1200', AccountKind::Asset);
    $gain = createFxAccount($company, '7690', AccountKind::Revenue);
    $loss = createFxAccount($company, '6690', AccountKind::Expense);

    ExchangeRate::query()->create([
        'from_currency' => 'USD',
        'to_currency' => 'EUR',
        'rate' => '0.90000000',
        'rate_date' => '2026-07-01',
        'source' => 'manual',
    ]);
    ExchangeRate::query()->create([
        'from_currency' => 'USD',
        'to_currency' => 'EUR',
        'rate' => '0.95000000',
        'rate_date' => '2026-07-31',
        'source' => 'manual',
    ]);

    createFxReceivableSchedule($company);

    $schedule = PaymentScheduleLine::query()
        ->where('company_id', $company->id)
        ->where('currency_doc', 'USD')
        ->where('status', PaymentScheduleStatus::Open->value)
        ->firstOrFail();

    expect((string) $schedule->amount_local)->toBe('900.0000')
        ->and(app(CurrencyConverter::class)->getRate('USD', 'EUR', CarbonImmutable::parse('2026-07-31 23:59:59')))->toBe(0.95);

    $entry = app(FxRevaluationService::class)->revalueOpenSchedules(
        (int) $company->id,
        CarbonImmutable::parse('2026-07-31 23:59:59'),
        (int) $receivables->id,
        (int) $gain->id,
        (int) $loss->id,
    );

    expect($entry)->not->toBeNull()
        ->and($entry->reference_type)->toBe('fx_revaluation')
        ->and($entry->lines)->toHaveCount(2)
        ->and((float) $entry->lines->firstWhere('account_id', $receivables->id)->amount_local)->toBe(50.0)
        ->and((float) $entry->lines->firstWhere('account_id', $gain->id)->amount_local)->toBe(-50.0);
});
