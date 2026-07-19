<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\ERP\Models\BankAccount;
use Modules\ERP\Models\BankStatement;
use Modules\ERP\Models\Company;

uses(RefreshDatabase::class);

it('discovers the ERP bank statement batch import command', function (): void {
    expect(Artisan::all())->toHaveKey('erp:bank-statements:import');
});

it('previews a bank file from the command without persistence', function (): void {
    $company = Company::query()->create([
        'slug' => 'batch-bank-command',
        'name' => 'Batch bank command',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $account = BankAccount::query()->create([
        'company_id' => $company->getKey(),
        'name' => 'Batch command account',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $fixture = __DIR__ . '/../../Stubs/banking/minimal.camt.xml';

    $this->artisan('erp:bank-statements:import', [
        '--bank-account' => $account->getKey(),
        '--path' => $fixture,
        '--format' => 'auto',
        '--dry-run' => true,
        '--output' => 'json',
    ])
        ->expectsOutputToContain('"previewed": 1')
        ->assertSuccessful();

    expect(BankStatement::query()->withoutGlobalScopes()->where('bank_account_id', $account->getKey())->count())->toBe(0);
});
