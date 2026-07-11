<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\PaymentRunStatus;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Models\BankAccount;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PartyBankAccount;
use Modules\ERP\Models\PaymentScheduleLine;
use Modules\ERP\Services\Payments\PaymentRunBuilderService;

uses(RefreshDatabase::class);

function createPaymentRunCompany(string $slug): Company
{
    return Company::query()->create([
        'slug' => $slug . '-' . uniqid(),
        'name' => 'Payment Run Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
}

function createPaymentRunSupplier(Company $company, string $name = 'Supplier Srl'): Party
{
    return Party::query()->create([
        'company_id' => $company->id,
        'name' => $name,
        'is_supplier' => true,
        'is_customer' => false,
    ]);
}

function createPaymentRunBankAccount(Company $company): BankAccount
{
    return BankAccount::query()->create([
        'company_id' => $company->id,
        'name' => 'Main Bank',
        'iban' => 'IT60X0542811101000000123456',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
}

function createPaymentRunScheduleLine(
    Company $company,
    Party $party,
    InvoiceDirection $direction = InvoiceDirection::Purchase,
    PaymentScheduleStatus $status = PaymentScheduleStatus::Open,
    string $amount_doc = '1200.0000',
    string $paid_amount_doc = '0.0000',
): PaymentScheduleLine {
    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => $direction,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
        'reference' => $direction === InvoiceDirection::Purchase ? 'SUP-2026-001' : 'CLI-2026-001',
        'posted_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
    ]);

    return PaymentScheduleLine::query()->create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'due_date' => '2026-07-31',
        'amount_doc' => $amount_doc,
        'currency_doc' => 'EUR',
        'amount_local' => $amount_doc,
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
        'paid_amount_doc' => $paid_amount_doc,
        'paid_amount_local' => $paid_amount_doc,
        'status' => $status,
    ]);
}

it('creates a draft supplier payment run from open purchase schedule lines', function (): void {
    $company = createPaymentRunCompany('pay-run-build');
    $supplier = createPaymentRunSupplier($company);
    $bank_account = createPaymentRunBankAccount($company);
    $supplier_bank = PartyBankAccount::query()->create([
        'company_id' => $company->id,
        'party_id' => $supplier->id,
        'beneficiary_name' => 'Supplier Srl',
        'iban' => 'IT02L1234512345123456789012',
        'bic' => 'BCITITMM',
        'currency' => 'EUR',
        'is_default' => true,
        'is_active' => true,
    ]);
    $schedule_line = createPaymentRunScheduleLine($company, $supplier);

    $run = app(PaymentRunBuilderService::class)->build(
        (int) $company->id,
        (int) $bank_account->id,
        [(int) $schedule_line->id],
        '2026-08-01',
    );

    expect($run->status)->toBe(PaymentRunStatus::Draft)
        ->and((string) $run->total_amount_doc)->toBe('1200.0000')
        ->and($run->lines)->toHaveCount(1)
        ->and((int) $run->lines->first()->party_bank_account_id)->toBe((int) $supplier_bank->id)
        ->and($run->lines->first()->beneficiary_name)->toBe('Supplier Srl')
        ->and($run->lines->first()->beneficiary_iban)->toBe('IT02L1234512345123456789012')
        ->and($run->lines->first()->remittance_information)->toContain('SUP-2026-001');
});

it('rejects receivable schedule lines for supplier payment runs', function (): void {
    $company = createPaymentRunCompany('pay-run-ar');
    $customer = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Customer Spa',
        'is_customer' => true,
        'is_supplier' => false,
    ]);
    $bank_account = createPaymentRunBankAccount($company);
    $schedule_line = createPaymentRunScheduleLine($company, $customer, InvoiceDirection::Sale);

    expect(fn () => app(PaymentRunBuilderService::class)->build(
        (int) $company->id,
        (int) $bank_account->id,
        [(int) $schedule_line->id],
        '2026-08-01',
    ))->toThrow(ValidationException::class);
});

it('rejects suppliers without an active default bank account', function (): void {
    $company = createPaymentRunCompany('pay-run-no-bank');
    $supplier = createPaymentRunSupplier($company);
    $bank_account = createPaymentRunBankAccount($company);
    $schedule_line = createPaymentRunScheduleLine($company, $supplier);

    expect(fn () => app(PaymentRunBuilderService::class)->build(
        (int) $company->id,
        (int) $bank_account->id,
        [(int) $schedule_line->id],
        '2026-08-01',
    ))->toThrow(ValidationException::class);
});

it('does not include fully paid schedule lines', function (): void {
    $company = createPaymentRunCompany('pay-run-paid');
    $supplier = createPaymentRunSupplier($company);
    $bank_account = createPaymentRunBankAccount($company);
    PartyBankAccount::query()->create([
        'company_id' => $company->id,
        'party_id' => $supplier->id,
        'beneficiary_name' => 'Supplier Srl',
        'iban' => 'IT02L1234512345123456789012',
        'currency' => 'EUR',
        'is_default' => true,
        'is_active' => true,
    ]);
    $schedule_line = createPaymentRunScheduleLine(
        company: $company,
        party: $supplier,
        status: PaymentScheduleStatus::Paid,
        paid_amount_doc: '1200.0000',
    );

    expect(fn () => app(PaymentRunBuilderService::class)->build(
        (int) $company->id,
        (int) $bank_account->id,
        [(int) $schedule_line->id],
        '2026-08-01',
    ))->toThrow(ValidationException::class);
});
