<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Models\BankAccount;
use Modules\ERP\Models\BankStatement;
use Modules\ERP\Models\BankStatementLine;
use Modules\ERP\Models\Company;
use Modules\ERP\Services\Banking\BankStatementCsvImporter;

uses(RefreshDatabase::class);

function createBankImportCompany(): Company
{
    return Company::query()->create([
        'slug' => 'bank-import',
        'name' => 'Bank Import',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
}

it('imports csv bank statement lines', function (): void {
    $company = createBankImportCompany();
    $account = BankAccount::query()->create([
        'company_id' => $company->id,
        'name' => 'Main bank',
        'iban' => 'IT60X0542811101000000123456',
        'currency' => 'EUR',
    ]);
    $statement = BankStatement::query()->create([
        'company_id' => $company->id,
        'bank_account_id' => $account->id,
        'source_filename' => 'statement.csv',
    ]);

    $path = tempnam(sys_get_temp_dir(), 'erp-bank-');
    file_put_contents($path, implode("\n", [
        'booked_at,value_at,reference,description,amount_doc,currency_doc',
        '2026-05-01,2026-05-02,TRX-1,Customer payment,125.50,EUR',
        '2026-05-03,,TRX-2,Bank fee,-5.25,EUR',
    ]));

    $created = app(BankStatementCsvImporter::class)->import($statement, $path);

    expect($created)->toBe(2)
        ->and(BankStatementLine::query()->where('bank_statement_id', $statement->id)->count())->toBe(2);

    $first = BankStatementLine::query()->where('reference', 'TRX-1')->firstOrFail();

    expect($first->company_id)->toBe($company->id)
        ->and((float) $first->amount_doc)->toBe(125.5)
        ->and($first->raw_payload['description'])->toBe('Customer payment');
});

it('rejects a CSV row missing the required date or amount', function (): void {
    $company = Company::query()->create([
        'slug' => 'csv-bad-row',
        'name' => 'CSV Bad Row',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $account = BankAccount::query()->create([
        'company_id' => $company->id,
        'name' => 'Main bank',
        'currency' => 'EUR',
    ]);
    $statement = BankStatement::query()->create([
        'company_id' => $company->id,
        'bank_account_id' => $account->id,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents(
        $path,
        "booked_at,description,amount_doc\n2026-05-01,Valid row,100.00\n,Missing date,50.00\n",
    );

    try {
        expect(fn () => app(BankStatementCsvImporter::class)->import($statement, $path))
            ->toThrow(ValidationException::class);

        // The whole import is transactional: nothing is persisted on a bad row.
        expect($statement->lines()->count())->toBe(0);
    } finally {
        @unlink($path);
    }
});

it('rejects CSV rows with malformed non-empty dates', function (): void {
    $company = Company::query()->create([
        'slug' => 'csv-malformed-date',
        'name' => 'CSV Malformed Date',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $account = BankAccount::query()->create([
        'company_id' => $company->id,
        'name' => 'Main bank',
        'currency' => 'EUR',
    ]);
    $statement = BankStatement::query()->create([
        'company_id' => $company->id,
        'bank_account_id' => $account->id,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents(
        $path,
        "booked_at,value_at,description,amount_doc\nnot-a-date,2026-05-02,Bad booked date,100.00\n",
    );

    try {
        expect(fn () => app(BankStatementCsvImporter::class)->import($statement, $path))
            ->toThrow(ValidationException::class, 'Row 1 contains an invalid booked date.');

        expect($statement->lines()->count())->toBe(0);
    } finally {
        @unlink($path);
    }
});

it('rejects csv files with duplicate header names', function (): void {
    $company = Company::query()->create([
        'slug' => 'csv-dup-header',
        'name' => 'CSV Dup Header',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $account = BankAccount::query()->create([
        'company_id' => $company->id,
        'name' => 'Main bank',
        'currency' => 'EUR',
    ]);
    $statement = BankStatement::query()->create([
        'company_id' => $company->id,
        'bank_account_id' => $account->id,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents(
        $path,
        "booked_at,booked_at,description,amount_doc\n2026-05-01,2026-05-01,Row,100.00\n",
    );

    try {
        expect(fn () => app(BankStatementCsvImporter::class)->import($statement, $path))
            ->toThrow(ValidationException::class, 'duplicate column names');
    } finally {
        @unlink($path);
    }
});
