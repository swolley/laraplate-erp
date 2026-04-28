<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Casts\AccountKind;
use Modules\Business\Casts\DocumentType;
use Modules\Business\Models\Account;
use Modules\Business\Models\Company;
use Modules\Business\Models\FiscalPeriod;
use Modules\Business\Models\FiscalYear;
use Modules\Business\Services\Accounting\DocumentNumberAllocator;
use Modules\Business\Services\Accounting\JournalPostingService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allocates sequential document numbers per company and stream', function (): void {
    $company = Company::query()->create([
        'slug' => 'acme',
        'name' => 'Acme',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => true,
    ]);

    $allocator = new DocumentNumberAllocator;

    expect($allocator->next($company, DocumentType::Quotation, 0))->toBe('00001')
        ->and($allocator->next($company, DocumentType::Quotation, 0))->toBe('00002')
        ->and($allocator->next($company, DocumentType::SalesInvoice, 2026))->toBe('2026-00001');
});

it('posts a balanced journal entry with lines', function (): void {
    $company = Company::query()->create([
        'slug' => 'demo',
        'name' => 'Demo',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);

    $debit = Account::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => '100',
        'name' => 'Cash',
        'kind' => AccountKind::Asset,
        'is_active' => true,
    ]);

    $credit = Account::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => '200',
        'name' => 'Payable',
        'kind' => AccountKind::Liability,
        'is_active' => true,
    ]);

    $service = new JournalPostingService;

    $entry = $service->post($company, [
        [
            'account_id' => (int) $debit->getKey(),
            'amount_doc' => '100.0000',
            'currency_doc' => 'EUR',
            'amount_local' => '100.0000',
            'currency_local' => 'EUR',
            'fx_rate' => '1',
        ],
        [
            'account_id' => (int) $credit->getKey(),
            'amount_doc' => '-100.0000',
            'currency_doc' => 'EUR',
            'amount_local' => '-100.0000',
            'currency_local' => 'EUR',
            'fx_rate' => '1',
        ],
    ], null, 'Opening');

    $entry->load('lines');

    expect($entry->lines)->toHaveCount(2)
        ->and((float) $entry->lines[0]->amount_local)->toBe(100.0)
        ->and((float) $entry->lines[1]->amount_local)->toBe(-100.0);
});

it('refuses posting into a closed fiscal period', function (): void {
    $company = Company::query()->create([
        'slug' => 'closed-co',
        'name' => 'Closed',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);

    $year = FiscalYear::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_closed' => false,
    ]);

    $period = FiscalPeriod::query()->create([
        'fiscal_year_id' => $year->id,
        'period_no' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'is_closed' => true,
    ]);

    $debit = Account::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'd1',
        'name' => 'D',
        'kind' => AccountKind::Asset,
        'is_active' => true,
    ]);

    $credit = Account::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'c1',
        'name' => 'C',
        'kind' => AccountKind::Liability,
        'is_active' => true,
    ]);

    $service = new JournalPostingService;

    expect(fn () => $service->post($company, [
        [
            'account_id' => (int) $debit->getKey(),
            'amount_doc' => '10',
            'currency_doc' => 'EUR',
            'amount_local' => '10',
            'currency_local' => 'EUR',
            'fx_rate' => '1',
        ],
        [
            'account_id' => (int) $credit->getKey(),
            'amount_doc' => '-10',
            'currency_doc' => 'EUR',
            'amount_local' => '-10',
            'currency_local' => 'EUR',
            'fx_rate' => '1',
        ],
    ], $period))->toThrow(\Modules\Business\Exceptions\PostingToClosedFiscalPeriodException::class);
});
