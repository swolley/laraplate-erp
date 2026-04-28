<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Casts\AccountKind;
use Modules\Business\Casts\DocumentType;
use Modules\Business\Exceptions\JournalAlreadyReversedException;
use Modules\Business\Exceptions\PostedJournalImmutableException;
use Modules\Business\Exceptions\PostingToClosedFiscalPeriodException;
use Modules\Business\Models\Account;
use Modules\Business\Models\Company;
use Modules\Business\Models\DocumentSequence;
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

it('seeds fiscal sequences with gap_allowed false and quotations with gap_allowed true', function (): void {
    $company = Company::query()->create([
        'slug' => 'gap-co',
        'name' => 'Gap',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);

    $allocator = new DocumentNumberAllocator;
    $allocator->next($company, DocumentType::SalesInvoice, 2026);
    $allocator->next($company, DocumentType::Quotation, 0);

    $invoice_row = DocumentSequence::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('document_type', DocumentType::SalesInvoice)
        ->first();
    $quote_row = DocumentSequence::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('document_type', DocumentType::Quotation)
        ->first();

    expect($invoice_row->gap_allowed)->toBeFalse()
        ->and($quote_row->gap_allowed)->toBeTrue();
});

it('uses format_pattern after manual update on the sequence row', function (): void {
    $company = Company::query()->create([
        'slug' => 'fmt-co',
        'name' => 'Fmt',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);

    $allocator = new DocumentNumberAllocator;
    $allocator->next($company, DocumentType::SalesInvoice, 2026);

    $row = DocumentSequence::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('document_type', DocumentType::SalesInvoice)
        ->firstOrFail();
    $row->update([
        'format_pattern' => '{prefix}{YYYY}/N{number}{suffix}',
        'prefix' => 'F',
        'suffix' => '-E',
    ]);

    expect($allocator->next($company, DocumentType::SalesInvoice, 2026))
        ->toBe('F2026/N00002-E');
});

it('produces many unique numbers on the same stream', function (): void {
    $company = Company::query()->create([
        'slug' => 'seq-co',
        'name' => 'Seq',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);

    $allocator = new DocumentNumberAllocator;
    $numbers = [];

    for ($i = 0; $i < 40; $i++) {
        $numbers[] = $allocator->next($company, DocumentType::InternalJournal, 0);
    }

    expect(count(array_unique($numbers)))->toBe(40);
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

it('reverses a posted entry with inverted lines and links the original id', function (): void {
    $company = Company::query()->create([
        'slug' => 'rev-co',
        'name' => 'Rev',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);

    $debit = Account::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'd10',
        'name' => 'D',
        'kind' => AccountKind::Asset,
        'is_active' => true,
    ]);

    $credit = Account::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'c10',
        'name' => 'C',
        'kind' => AccountKind::Liability,
        'is_active' => true,
    ]);

    $service = new JournalPostingService;

    $original = $service->post($company, [
        [
            'account_id' => (int) $debit->getKey(),
            'amount_doc' => '40.0000',
            'currency_doc' => 'EUR',
            'amount_local' => '40.0000',
            'currency_local' => 'EUR',
            'fx_rate' => '1',
        ],
        [
            'account_id' => (int) $credit->getKey(),
            'amount_doc' => '-40.0000',
            'currency_doc' => 'EUR',
            'amount_local' => '-40.0000',
            'currency_local' => 'EUR',
            'fx_rate' => '1',
        ],
    ], null, 'Accrual');

    $storno = $service->reverse($original, $company, 'Correction');

    expect($storno->reverses_journal_entry_id)->toBe($original->id)
        ->and($storno->reversal_reason)->toBe('Correction')
        ->and((float) $storno->lines[0]->amount_local)->toBe(-40.0)
        ->and((float) $storno->lines[1]->amount_local)->toBe(40.0);
});

it('refuses a second reversal for the same original entry', function (): void {
    $company = Company::query()->create([
        'slug' => 'rev2-co',
        'name' => 'Rev2',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);

    $debit = Account::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'dx',
        'name' => 'D',
        'kind' => AccountKind::Asset,
        'is_active' => true,
    ]);

    $credit = Account::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'cx',
        'name' => 'C',
        'kind' => AccountKind::Liability,
        'is_active' => true,
    ]);

    $service = new JournalPostingService;

    $original = $service->post($company, [
        [
            'account_id' => (int) $debit->getKey(),
            'amount_doc' => '5',
            'currency_doc' => 'EUR',
            'amount_local' => '5',
            'currency_local' => 'EUR',
            'fx_rate' => '1',
        ],
        [
            'account_id' => (int) $credit->getKey(),
            'amount_doc' => '-5',
            'currency_doc' => 'EUR',
            'amount_local' => '-5',
            'currency_local' => 'EUR',
            'fx_rate' => '1',
        ],
    ], null, 'X');

    $service->reverse($original, $company, 'Once');

    expect(fn () => $service->reverse($original, $company, 'Twice'))
        ->toThrow(JournalAlreadyReversedException::class);
});

it('blocks direct updates to posted journal headers', function (): void {
    $company = Company::query()->create([
        'slug' => 'im-co',
        'name' => 'Im',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);

    $debit = Account::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'id',
        'name' => 'D',
        'kind' => AccountKind::Asset,
        'is_active' => true,
    ]);

    $credit = Account::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'ic',
        'name' => 'C',
        'kind' => AccountKind::Liability,
        'is_active' => true,
    ]);

    $service = new JournalPostingService;

    $entry = $service->post($company, [
        [
            'account_id' => (int) $debit->getKey(),
            'amount_doc' => '1',
            'currency_doc' => 'EUR',
            'amount_local' => '1',
            'currency_local' => 'EUR',
            'fx_rate' => '1',
        ],
        [
            'account_id' => (int) $credit->getKey(),
            'amount_doc' => '-1',
            'currency_doc' => 'EUR',
            'amount_local' => '-1',
            'currency_local' => 'EUR',
            'fx_rate' => '1',
        ],
    ], null, 'P');

    expect(fn () => $entry->update(['description' => 'Hacked']))->toThrow(PostedJournalImmutableException::class);
});

it('blocks direct updates to lines of posted journals', function (): void {
    $company = Company::query()->create([
        'slug' => 'iml-co',
        'name' => 'Iml',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);

    $debit = Account::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'idl',
        'name' => 'D',
        'kind' => AccountKind::Asset,
        'is_active' => true,
    ]);

    $credit = Account::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'icl',
        'name' => 'C',
        'kind' => AccountKind::Liability,
        'is_active' => true,
    ]);

    $service = new JournalPostingService;

    $entry = $service->post($company, [
        [
            'account_id' => (int) $debit->getKey(),
            'amount_doc' => '2',
            'currency_doc' => 'EUR',
            'amount_local' => '2',
            'currency_local' => 'EUR',
            'fx_rate' => '1',
        ],
        [
            'account_id' => (int) $credit->getKey(),
            'amount_doc' => '-2',
            'currency_doc' => 'EUR',
            'amount_local' => '-2',
            'currency_local' => 'EUR',
            'fx_rate' => '1',
        ],
    ], null, 'L');

    $line = $entry->lines->first();
    expect(fn () => $line->update(['description' => 'x']))->toThrow(PostedJournalImmutableException::class);
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
    ], $period))->toThrow(PostingToClosedFiscalPeriodException::class);
});
