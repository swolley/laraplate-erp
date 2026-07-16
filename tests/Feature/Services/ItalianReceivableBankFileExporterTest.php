<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Models\BankAccount;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PartyBankAccount;
use Modules\ERP\Models\PaymentScheduleLine;
use Modules\ERP\Services\Payments\ItalianReceivableBankFileExporter;

uses(RefreshDatabase::class);

function createItalianReceivableCompany(): Company
{
    return Company::query()->create([
        'slug' => 'it-receivable-' . uniqid(),
        'name' => 'Italian Receivable Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
}

function createItalianReceivableBankAccount(Company $company): BankAccount
{
    return BankAccount::query()->create([
        'company_id' => $company->id,
        'name' => 'Collection Bank',
        'iban' => 'IT60X0542811101000000123456',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
}

function createItalianReceivableScheduleLine(Company $company, bool $with_mandate = true): PaymentScheduleLine
{
    $customer = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Customer Spa',
        'is_customer' => true,
        'is_supplier' => false,
    ]);

    PartyBankAccount::query()->create([
        'company_id' => $company->id,
        'party_id' => $customer->id,
        'beneficiary_name' => 'Customer Spa',
        'iban' => 'IT02L1234512345123456789012',
        'bic' => 'BCITITMM',
        'currency' => 'EUR',
        'direct_debit_mandate_reference' => $with_mandate ? 'MANDATE-001' : null,
        'direct_debit_mandate_signed_on' => $with_mandate ? '2026-01-15' : null,
        'direct_debit_mandate_scheme' => $with_mandate ? 'CORE' : null,
        'is_default' => true,
        'is_active' => true,
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $customer->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
        'reference' => 'CLI-2026-001',
        'posted_at' => now(),
    ]);

    return PaymentScheduleLine::query()->create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'due_date' => '2026-08-31',
        'amount_doc' => '850.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '850.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
        'paid_amount_doc' => '0.0000',
        'paid_amount_local' => '0.0000',
        'status' => PaymentScheduleStatus::Open,
    ]);
}

it('exports customer receivables as RiBa text', function (): void {
    $company = createItalianReceivableCompany();
    $bank_account = createItalianReceivableBankAccount($company);
    $line = createItalianReceivableScheduleLine($company);

    $content = app(ItalianReceivableBankFileExporter::class)->exportRiba([$line], $bank_account);

    expect($content)->toContain('IBRIBA')
        ->and($content)->toContain('20CLI-2026-001')
        ->and($content)->toContain('CUSTOMER SPA')
        ->and($content)->toContain('20260831')
        ->and($content)->toContain('000000000085000')
        ->and($content)->toContain('EF0000001');
});

it('exports customer receivables as SEPA SDD CORE pain008 XML', function (): void {
    $company = createItalianReceivableCompany();
    $bank_account = createItalianReceivableBankAccount($company);
    $line = createItalianReceivableScheduleLine($company);

    $xml = app(ItalianReceivableBankFileExporter::class)->exportSddCore([$line], $bank_account, 'IT98ZZZ09999999999');

    expect($xml)->toContain('CstmrDrctDbtInitn')
        ->and($xml)->toContain('pain.008.001.02')
        ->and($xml)->toContain('<MndtId>MANDATE-001</MndtId>')
        ->and($xml)->toContain('<DtOfSgntr>2026-01-15</DtOfSgntr>')
        ->and($xml)->toContain('<IBAN>IT02L1234512345123456789012</IBAN>')
        ->and($xml)->toContain('Ccy="EUR">850.00');
});

it('blocks SDD export when the customer mandate is missing', function (): void {
    $company = createItalianReceivableCompany();
    $bank_account = createItalianReceivableBankAccount($company);
    $line = createItalianReceivableScheduleLine($company, with_mandate: false);

    expect(fn () => app(ItalianReceivableBankFileExporter::class)->exportSddCore([$line], $bank_account, 'IT98ZZZ09999999999'))
        ->toThrow(ValidationException::class);
});
