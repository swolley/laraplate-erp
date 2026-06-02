<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\BankStatementLineStatus;
use Modules\ERP\Casts\PaymentDirection;
use Modules\ERP\Models\BankAccount;
use Modules\ERP\Models\BankStatement;
use Modules\ERP\Models\BankStatementLine;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\Payment;
use Modules\ERP\Services\Banking\BankReconciliationService;

uses(RefreshDatabase::class);

function createBankReconciliationCompany(): array
{
    $company = Company::query()->create([
        'slug' => 'bank-reco',
        'name' => 'Bank Reco',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Customer',
        'is_customer' => true,
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

    return [$company, $party, $account, $statement];
}

it('matches and unmatches a bank statement line to a payment', function (): void {
    [$company, $party, $account, $statement] = createBankReconciliationCompany();

    $line = BankStatementLine::query()->create([
        'company_id' => $company->id,
        'bank_statement_id' => $statement->id,
        'booked_at' => '2026-05-01',
        'amount_doc' => '100.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '100.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
    ]);
    $payment = Payment::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => PaymentDirection::Inbound,
        'payment_date' => '2026-05-01',
        'amount_doc' => '100.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '100.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
    ]);

    $matched = app(BankReconciliationService::class)->matchPayment($line, $payment);

    expect($matched->status)->toBe(BankStatementLineStatus::Matched)
        ->and((int) $matched->matched_payment_id)->toBe((int) $payment->id)
        ->and((int) $payment->fresh()->bank_account_id)->toBe((int) $account->id);

    $unmatched = app(BankReconciliationService::class)->unmatch($matched);

    expect($unmatched->status)->toBe(BankStatementLineStatus::Imported)
        ->and($unmatched->matched_payment_id)->toBeNull();
});

it('rejects payment matches with incompatible amount direction', function (): void {
    [$company, $party, , $statement] = createBankReconciliationCompany();

    $line = BankStatementLine::query()->create([
        'company_id' => $company->id,
        'bank_statement_id' => $statement->id,
        'booked_at' => '2026-05-01',
        'amount_doc' => '-100.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '-100.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
    ]);
    $payment = Payment::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => PaymentDirection::Inbound,
        'payment_date' => '2026-05-01',
        'amount_doc' => '100.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '100.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
    ]);

    expect(fn () => app(BankReconciliationService::class)->matchPayment($line, $payment))
        ->toThrow(ValidationException::class);
});

it('suggests compatible payments ordered by strongest statement line match', function (): void {
    [$company, $party, $account, $statement] = createBankReconciliationCompany();
    $other_company = Company::query()->create([
        'slug' => 'bank-reco-other',
        'name' => 'Bank Reco Other',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $other_party = Party::query()->create([
        'company_id' => $other_company->id,
        'name' => 'Other Customer',
        'is_customer' => true,
    ]);

    $line = BankStatementLine::query()->create([
        'company_id' => $company->id,
        'bank_statement_id' => $statement->id,
        'booked_at' => '2026-05-10',
        'amount_doc' => '100.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '100.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
        'reference' => 'INV-100',
    ]);
    $exact = Payment::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => PaymentDirection::Inbound,
        'payment_date' => '2026-05-10',
        'amount_doc' => '100.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '100.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
        'reference' => 'INV-100',
        'bank_account_id' => $account->id,
    ]);
    $near = Payment::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => PaymentDirection::Inbound,
        'payment_date' => '2026-05-13',
        'amount_doc' => '100.5000',
        'currency_doc' => 'EUR',
        'amount_local' => '100.5000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
    ]);
    Payment::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => PaymentDirection::Inbound,
        'payment_date' => '2026-05-25',
        'amount_doc' => '100.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '100.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
    ]);
    Payment::query()->create([
        'company_id' => $other_company->id,
        'party_id' => $other_party->id,
        'direction' => PaymentDirection::Inbound,
        'payment_date' => '2026-05-10',
        'amount_doc' => '100.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '100.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
        'reference' => 'INV-100',
    ]);

    $suggestions = app(BankReconciliationService::class)->suggestPayments($line);

    expect($suggestions->pluck('id')->all())->toBe([$exact->id, $near->id]);
});
