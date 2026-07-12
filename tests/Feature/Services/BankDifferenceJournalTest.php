<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Casts\BankStatementLineStatus;
use Modules\ERP\Casts\PaymentDirection;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\BankAccount;
use Modules\ERP\Models\BankStatement;
use Modules\ERP\Models\BankStatementLine;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\Payment;
use Modules\ERP\Services\Banking\BankReconciliationService;

uses(RefreshDatabase::class);

function createBankDifferenceFixture(string $line_amount = '100.5000', PaymentDirection $direction = PaymentDirection::Inbound): array
{
    $company = Company::query()->create([
        'slug' => 'bank-diff-' . uniqid(),
        'name' => 'Bank Difference Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $fiscal_year = FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    $fiscal_period = FiscalPeriod::query()->create([
        'fiscal_year_id' => $fiscal_year->id,
        'period_no' => 5,
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-31',
    ]);
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Statement Party',
        'is_customer' => $direction === PaymentDirection::Inbound,
        'is_supplier' => $direction === PaymentDirection::Outbound,
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
        'amount_doc' => $line_amount,
        'currency_doc' => 'EUR',
        'amount_local' => $line_amount,
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
        'reference' => 'BANK-DIFF',
    ]);
    $payment = Payment::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => $direction,
        'payment_date' => '2026-05-10',
        'amount_doc' => '100.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '100.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
        'reference' => 'PAY-DIFF',
    ]);
    $bank_cash = Account::query()->create([
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

    return [$company, $fiscal_period, $bank_account, $line, $payment, $bank_cash, $difference_expense];
}

it('matches a payment with a bank statement difference and posts a balanced journal', function (): void {
    [, $fiscal_period, $bank_account, $line, $payment, $bank_cash, $difference_expense] = createBankDifferenceFixture();

    $matched = app(BankReconciliationService::class)->matchPaymentWithDifference(
        $line,
        $payment,
        (int) $difference_expense->id,
    );

    $entry = JournalEntry::query()->with('lines')->findOrFail($matched->difference_journal_entry_id);
    $lines_by_account = $entry->lines->keyBy('account_id');

    expect($matched->status)->toBe(BankStatementLineStatus::Matched)
        ->and((int) $matched->matched_payment_id)->toBe((int) $payment->id)
        ->and((int) $matched->difference_journal_entry_id)->toBe((int) $entry->id)
        ->and((int) $payment->fresh()->bank_account_id)->toBe((int) $bank_account->id)
        ->and((int) $entry->fiscal_period_id)->toBe((int) $fiscal_period->id)
        ->and($entry->lines)->toHaveCount(2)
        ->and((float) $lines_by_account[(int) $bank_cash->id]->amount_doc)->toBe(0.5)
        ->and((float) $lines_by_account[(int) $difference_expense->id]->amount_doc)->toBe(-0.5);
});

it('posts outbound bank fees as expense and bank reduction', function (): void {
    [, , , $line, $payment, $bank_cash, $difference_expense] = createBankDifferenceFixture('-100.5000', PaymentDirection::Outbound);

    $matched = app(BankReconciliationService::class)->matchPaymentWithDifference(
        $line,
        $payment,
        (int) $difference_expense->id,
    );

    $entry = JournalEntry::query()->with('lines')->findOrFail($matched->difference_journal_entry_id);
    $lines_by_account = $entry->lines->keyBy('account_id');

    expect((float) $lines_by_account[(int) $bank_cash->id]->amount_doc)->toBe(-0.5)
        ->and((float) $lines_by_account[(int) $difference_expense->id]->amount_doc)->toBe(0.5);
});

it('rejects match with difference when no difference exists', function (): void {
    [, , , $line, $payment, , $difference_expense] = createBankDifferenceFixture('100.0000');

    expect(fn () => app(BankReconciliationService::class)->matchPaymentWithDifference(
        $line,
        $payment,
        (int) $difference_expense->id,
    ))->toThrow(ValidationException::class);
});

it('rejects difference journals posted to non expense accounts', function (): void {
    [$company, , , $line, $payment] = createBankDifferenceFixture();
    $asset_account = Account::query()->create([
        'company_id' => $company->id,
        'code' => '1200',
        'name' => 'Wrong account',
        'kind' => AccountKind::Asset,
        'is_active' => true,
    ]);

    expect(fn () => app(BankReconciliationService::class)->matchPaymentWithDifference(
        $line,
        $payment,
        (int) $asset_account->id,
    ))->toThrow(ValidationException::class);
});
