<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Models\BankAccount;
use Modules\ERP\Models\BankStatement;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Payment;

uses(RefreshDatabase::class);

it('defines statements and payments relationships', function (): void {
    $account = new BankAccount;

    expect($account->statements())->toBeInstanceOf(HasMany::class)
        ->and($account->payments())->toBeInstanceOf(HasMany::class)
        ->and($account->statements()->getRelated())->toBeInstanceOf(BankStatement::class)
        ->and($account->payments()->getRelated())->toBeInstanceOf(Payment::class);
});

it('loads related statements for a bank account', function (): void {
    $company = Company::query()->create([
        'slug' => 'bank-acct-' . uniqid(),
        'name' => 'Bank Account Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $account = BankAccount::query()->create([
        'company_id' => $company->id,
        'name' => 'Main',
        'currency' => 'EUR',
    ]);
    BankStatement::query()->create([
        'company_id' => $company->id,
        'bank_account_id' => $account->id,
    ]);

    expect($account->statements)->toHaveCount(1);
});
