<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Casts\MovementType;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Movement;
use Modules\ERP\Services\Cash\CashBalanceService;
use Modules\ERP\Services\Cash\MovementPostingService;

uses(RefreshDatabase::class);

/** @return array{Company, Account, Account, Account} */
function movementPostingFixture(): array
{
    $company = Company::query()->create([
        'slug' => 'cash-movement-' . uniqid(),
        'name' => 'Cash movement',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $bank = Account::query()->create([
        'company_id' => $company->id,
        'code' => '1103',
        'name' => 'Bank',
        'kind' => AccountKind::Asset,
        'meta' => ['erp_role' => 'bank_cash'],
        'is_active' => true,
    ]);
    $revenue = Account::query()->create([
        'company_id' => $company->id,
        'code' => '4201',
        'name' => 'Other revenue',
        'kind' => AccountKind::Revenue,
        'is_active' => true,
    ]);
    $expense = Account::query()->create([
        'company_id' => $company->id,
        'code' => '5603',
        'name' => 'Bank costs',
        'kind' => AccountKind::Expense,
        'is_active' => true,
    ]);

    return [$company, $bank, $revenue, $expense];
}

it('posts income and expense movements as balanced journals and derives cash balance', function (): void {
    [$company, $bank, $revenue, $expense] = movementPostingFixture();
    $income = Movement::query()->create([
        'company_id' => $company->id,
        'type' => MovementType::Income,
        'occurred_on' => '2026-07-01',
        'amount_doc' => '100.0000',
        'currency_doc' => 'EUR',
        'counterparty_account_id' => $revenue->id,
        'description' => 'Cash income',
    ]);
    $expense_movement = Movement::query()->create([
        'company_id' => $company->id,
        'type' => MovementType::Expense,
        'occurred_on' => '2026-07-02',
        'amount_doc' => '30.0000',
        'currency_doc' => 'EUR',
        'counterparty_account_id' => $expense->id,
        'description' => 'Cash expense',
    ]);
    $service = app(MovementPostingService::class);

    $income_entry = $service->post($income);
    $expense_entry = $service->post($expense_movement);
    $income_lines = $income_entry->lines->keyBy('account_id');
    $expense_lines = $expense_entry->lines->keyBy('account_id');

    expect((float) $income_lines[(int) $bank->id]->amount_local)->toBe(100.0)
        ->and((float) $income_lines[(int) $revenue->id]->amount_local)->toBe(-100.0)
        ->and((float) $expense_lines[(int) $expense->id]->amount_local)->toBe(30.0)
        ->and((float) $expense_lines[(int) $bank->id]->amount_local)->toBe(-30.0)
        ->and($income->fresh()->posted_journal_entry_id)->toBe($income_entry->id)
        ->and(app(CashBalanceService::class)->balance($company))->toBe('70.0000');
});

it('is idempotent and rejects a counterparty with the wrong account kind', function (): void {
    [$company, , $revenue, $expense] = movementPostingFixture();
    $income = Movement::query()->create([
        'company_id' => $company->id,
        'type' => MovementType::Income,
        'occurred_on' => '2026-07-01',
        'amount_doc' => '20.0000',
        'currency_doc' => 'EUR',
        'counterparty_account_id' => $revenue->id,
    ]);
    $invalid = Movement::query()->create([
        'company_id' => $company->id,
        'type' => MovementType::Income,
        'occurred_on' => '2026-07-01',
        'amount_doc' => '10.0000',
        'currency_doc' => 'EUR',
        'counterparty_account_id' => $expense->id,
    ]);
    $service = app(MovementPostingService::class);

    $first = $service->post($income);
    $second = $service->post($income->fresh());

    expect($second->id)->toBe($first->id)
        ->and(fn () => $service->post($invalid))->toThrow(ValidationException::class);
});

it('migrates pending movements through an idempotent command with dry-run support', function (): void {
    [$company, , $revenue] = movementPostingFixture();
    $movement = Movement::query()->create([
        'company_id' => $company->id,
        'type' => MovementType::Income,
        'occurred_on' => '2026-07-01',
        'amount_doc' => '15.0000',
        'currency_doc' => 'EUR',
        'counterparty_account_id' => $revenue->id,
    ]);

    $this->artisan('erp:migrate-movements-to-journal', ['--company' => $company->id, '--dry-run' => true])
        ->expectsOutput('1 movement(s) would be posted.')
        ->assertSuccessful();
    expect($movement->fresh()->posted_journal_entry_id)->toBeNull();

    $this->artisan('erp:migrate-movements-to-journal', ['--company' => $company->id])
        ->expectsOutput('1 movement(s) posted; 0 failed.')
        ->assertSuccessful();
    $this->artisan('erp:migrate-movements-to-journal', ['--company' => $company->id])
        ->expectsOutput('0 movement(s) posted; 0 failed.')
        ->assertSuccessful();

    expect($movement->fresh()->posted_journal_entry_id)->not->toBeNull();
});
