<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Models\BankAccount;
use Modules\ERP\Models\BankStatement;
use Modules\ERP\Models\BankStatementLine;
use Modules\ERP\Models\Company;
use Modules\ERP\Services\Banking\BankStatementBatchImportService;

uses(RefreshDatabase::class);

function batchBankAccount(): BankAccount
{
    $company = Company::query()->create([
        'slug' => 'batch-bank-' . uniqid(),
        'name' => 'Batch bank',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    return BankAccount::query()->create([
        'company_id' => $company->getKey(),
        'name' => 'Batch account',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
}

function batchBankFixture(string $filename): string
{
    return __DIR__ . '/../../Stubs/banking/' . $filename;
}

it('previews a CAMT file without writing statements or lines', function (): void {
    $account = batchBankAccount();

    $result = app(BankStatementBatchImportService::class)->import(
        $account,
        [batchBankFixture('minimal.camt.xml')],
        dry_run: true,
    );

    expect($result['summary'])->toMatchArray(['previewed' => 1, 'failed' => 0, 'lines' => 2])
        ->and(BankStatement::query()->withoutGlobalScopes()->where('bank_account_id', $account->getKey())->count())->toBe(0)
        ->and(BankStatementLine::query()->withoutGlobalScopes()->where('company_id', $account->company_id)->count())->toBe(0);
});

it('imports a file once and skips the same checksum on the second run', function (): void {
    $account = batchBankAccount();
    $path = batchBankFixture('minimal.mt940.sta');
    $service = app(BankStatementBatchImportService::class);

    $first = $service->import($account, [$path]);
    $second = $service->import($account, [$path]);
    $statement = BankStatement::query()->withoutGlobalScopes()->where('bank_account_id', $account->getKey())->firstOrFail();

    expect($first['summary'])->toMatchArray(['imported' => 1, 'failed' => 0, 'lines' => 2])
        ->and($second['summary'])->toMatchArray(['skipped' => 1, 'failed' => 0, 'lines' => 0])
        ->and($statement->source_checksum)->toBe(hash_file('sha256', $path))
        ->and($statement->period_start?->toDateString())->toBe('2026-05-01')
        ->and($statement->period_end?->toDateString())->toBe('2026-05-03')
        ->and($statement->lines()->count())->toBe(2);
});
