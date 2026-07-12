<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Casts\PaymentDirection;
use Modules\ERP\Filament\Pages\BankReconciliationPage;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\BankAccount;
use Modules\ERP\Models\BankStatement;
use Modules\ERP\Models\BankStatementLine;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\Payment;

uses(RefreshDatabase::class);

function createBankReconciliationDifferencePageFixture(): array
{
    $company = Company::query()->create([
        'slug' => 'bank-diff-page-' . uniqid(),
        'name' => 'Bank Difference Page Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $fiscal_year = FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    FiscalPeriod::query()->create([
        'fiscal_year_id' => $fiscal_year->id,
        'period_no' => 5,
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-31',
    ]);
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Customer',
        'is_customer' => true,
    ]);
    Account::query()->create([
        'company_id' => $company->id,
        'code' => '1103',
        'name' => 'Bank cash',
        'kind' => AccountKind::Asset,
        'meta' => ['erp_role' => 'bank_cash'],
        'is_active' => true,
    ]);
    $difference_expense = Account::query()->create([
        'company_id' => $company->id,
        'code' => '5702',
        'name' => 'Bank reconciliation differences',
        'kind' => AccountKind::Expense,
        'meta' => ['erp_role' => 'bank_reconciliation_difference'],
        'is_active' => true,
    ]);
    $bank_account = BankAccount::query()->create([
        'company_id' => $company->id,
        'name' => 'Main bank',
        'currency' => 'EUR',
    ]);
    $statement = BankStatement::query()->create([
        'company_id' => $company->id,
        'bank_account_id' => $bank_account->id,
    ]);
    $line = BankStatementLine::query()->create([
        'company_id' => $company->id,
        'bank_statement_id' => $statement->id,
        'booked_at' => '2026-05-10',
        'amount_doc' => '100.5000',
        'currency_doc' => 'EUR',
        'amount_local' => '100.5000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
    ]);
    $payment = Payment::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => PaymentDirection::Inbound,
        'payment_date' => '2026-05-10',
        'amount_doc' => '100.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '100.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
    ]);

    return [$line, $payment, $difference_expense];
}

it('matches a bank statement line with a difference from the reconciliation page', function (): void {
    [$line, $payment, $difference_expense] = createBankReconciliationDifferencePageFixture();

    Livewire::test(BankReconciliationPage::class)
        ->set('data.bank_statement_line_id', (int) $line->id)
        ->set('data.payment_id', (int) $payment->id)
        ->set('data.expense_account_id', (int) $difference_expense->id)
        ->call('matchWithDifference')
        ->assertHasNoErrors();

    $fresh = BankStatementLine::query()->findOrFail($line->id);

    expect($fresh->matched_payment_id)->not->toBeNull()
        ->and($fresh->difference_journal_entry_id)->not->toBeNull();
});
