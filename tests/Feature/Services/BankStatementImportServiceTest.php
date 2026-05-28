<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
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
