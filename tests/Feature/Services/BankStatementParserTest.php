<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Models\BankAccount;
use Modules\ERP\Models\BankStatement;
use Modules\ERP\Models\BankStatementLine;
use Modules\ERP\Models\Company;
use Modules\ERP\Services\Banking\BankStatementImportService;
use Modules\ERP\Services\Banking\Camt053Parser;
use Modules\ERP\Services\Banking\Mt940Parser;

uses(RefreshDatabase::class);

function bankParserFixturePath(string $filename): string
{
    return __DIR__ . '/../../Stubs/banking/' . $filename;
}

function createBankParserStatement(): BankStatement
{
    $company = Company::query()->create([
        'slug' => 'bank-parser-' . uniqid(),
        'name' => 'Bank Parser Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $account = BankAccount::query()->create([
        'company_id' => $company->id,
        'name' => 'Main bank',
        'iban' => 'IT60X0542811101000000123456',
        'currency' => 'EUR',
    ]);

    return BankStatement::query()->create([
        'company_id' => $company->id,
        'bank_account_id' => $account->id,
        'source_filename' => 'statement',
    ]);
}

it('parses minimal CAMT.053 statement entries', function (): void {
    $path = bankParserFixturePath('minimal.camt.xml');
    $lines = app(Camt053Parser::class)->parse(file_get_contents($path));

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->booked_at)->toBe('2026-05-01')
        ->and($lines[0]->value_at)->toBe('2026-05-02')
        ->and($lines[0]->reference)->toBe('INV-100')
        ->and($lines[0]->description)->toBe('Customer payment')
        ->and($lines[0]->amount_doc)->toBe('125.5000')
        ->and($lines[0]->currency_doc)->toBe('EUR')
        ->and($lines[1]->reference)->toBe('CAMT-FEE-1')
        ->and($lines[1]->description)->toBe('Bank fee')
        ->and($lines[1]->amount_doc)->toBe('-5.2500');
});

it('parses minimal MT940 statement transactions', function (): void {
    $path = bankParserFixturePath('minimal.mt940.sta');
    $lines = app(Mt940Parser::class)->parse(file_get_contents($path));

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->booked_at)->toBe('2026-05-01')
        ->and($lines[0]->value_at)->toBe('2026-05-01')
        ->and($lines[0]->reference)->toBe('MT940-TRX-1')
        ->and($lines[0]->description)->toBe('Customer payment')
        ->and($lines[0]->amount_doc)->toBe('125.5000')
        ->and($lines[0]->currency_doc)->toBe('EUR')
        ->and($lines[1]->booked_at)->toBe('2026-05-03')
        ->and($lines[1]->reference)->toBe('MT940-FEE-1')
        ->and($lines[1]->description)->toBe('Bank fee')
        ->and($lines[1]->amount_doc)->toBe('-5.2500');
});

it('auto-detects CAMT.053 files and persists imported lines', function (): void {
    $statement = createBankParserStatement();
    $path = bankParserFixturePath('minimal.camt.xml');

    $created = app(BankStatementImportService::class)->importFile($statement, $path);

    $first = BankStatementLine::query()
        ->where('bank_statement_id', $statement->id)
        ->where('reference', 'INV-100')
        ->firstOrFail();

    expect($created)->toBe(2)
        ->and(BankStatementLine::query()->where('bank_statement_id', $statement->id)->count())->toBe(2)
        ->and((float) $first->amount_doc)->toBe(125.5)
        ->and($first->raw_payload['format'])->toBe('camt053')
        ->and($statement->fresh()->imported_at)->not->toBeNull();
});

it('auto-detects MT940 files and persists imported lines', function (): void {
    $statement = createBankParserStatement();
    $path = bankParserFixturePath('minimal.mt940.sta');

    $created = app(BankStatementImportService::class)->importFile($statement, $path);

    $fee = BankStatementLine::query()
        ->where('bank_statement_id', $statement->id)
        ->where('reference', 'MT940-FEE-1')
        ->firstOrFail();

    expect($created)->toBe(2)
        ->and((float) $fee->amount_doc)->toBe(-5.25)
        ->and($fee->description)->toBe('Bank fee')
        ->and($fee->raw_payload['format'])->toBe('mt940');
});
