<?php

declare(strict_types=1);

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
use Modules\ERP\Services\Payments\SepaPain001Exporter;

uses(RefreshDatabase::class);

function createSepaExporterCompany(): Company
{
    return Company::query()->create([
        'slug' => 'sepa-export-' . uniqid(),
        'name' => 'SEPA Export Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
}

function createSepaExporterSupplier(Company $company): Party
{
    return Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Supplier Srl',
        'is_supplier' => true,
        'is_customer' => false,
    ]);
}

function createSepaExporterBankAccount(Company $company): BankAccount
{
    return BankAccount::query()->create([
        'company_id' => $company->id,
        'name' => 'Main Bank',
        'iban' => 'IT60X0542811101000000123456',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
}

function createSepaExporterScheduleLine(Company $company, Party $supplier): PaymentScheduleLine
{
    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $supplier->id,
        'direction' => InvoiceDirection::Purchase,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
        'reference' => 'SUP-2026-001',
        'posted_at' => now(),
    ]);

    return PaymentScheduleLine::query()->create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'due_date' => '2026-07-31',
        'amount_doc' => '1200.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '1200.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
        'paid_amount_doc' => '0.0000',
        'paid_amount_local' => '0.0000',
        'status' => PaymentScheduleStatus::Open,
    ]);
}

function createSepaExporterRun(PaymentRunStatus $status = PaymentRunStatus::Approved): Modules\ERP\Models\PaymentRun
{
    $company = createSepaExporterCompany();
    $supplier = createSepaExporterSupplier($company);
    $bank_account = createSepaExporterBankAccount($company);
    PartyBankAccount::query()->create([
        'company_id' => $company->id,
        'party_id' => $supplier->id,
        'beneficiary_name' => 'Supplier Srl',
        'iban' => 'IT02L1234512345123456789012',
        'bic' => 'BCITITMM',
        'currency' => 'EUR',
        'is_default' => true,
        'is_active' => true,
    ]);
    $schedule_line = createSepaExporterScheduleLine($company, $supplier);

    $run = app(PaymentRunBuilderService::class)->build(
        (int) $company->id,
        (int) $bank_account->id,
        [(int) $schedule_line->id],
        '2026-08-01',
    );
    $run->status = $status;
    $run->approved_at = $status === PaymentRunStatus::Approved ? now() : null;
    $run->save();

    return $run;
}

it('exports a payment run as SEPA pain001 XML', function (): void {
    $run = createSepaExporterRun();

    $xml = app(SepaPain001Exporter::class)->export($run);

    expect($xml)->toContain('<?xml version="1.0" encoding="UTF-8"?>')
        ->and($xml)->toContain('CstmrCdtTrfInitn')
        ->and($xml)->toContain('IT60X0542811101000000123456')
        ->and($xml)->toContain('IT02L1234512345123456789012')
        ->and($xml)->toContain('Ccy="EUR">1200.00')
        ->and($xml)->toContain('2026-08-01')
        ->and($xml)->toContain('SUP-2026-001');
});

it('refuses to export draft or cancelled payment runs', function (): void {
    $run = createSepaExporterRun(PaymentRunStatus::Draft);

    expect(fn () => app(SepaPain001Exporter::class)->export($run))
        ->toThrow(ValidationException::class);
});

it('marks the payment run exported with checksum metadata', function (): void {
    $run = createSepaExporterRun();

    app(SepaPain001Exporter::class)->export($run);

    $fresh = $run->fresh('lines');

    expect($fresh->status)->toBe(PaymentRunStatus::Exported)
        ->and($fresh->exported_at)->not->toBeNull()
        ->and($fresh->export_file_name)->toEndWith('.xml')
        ->and($fresh->export_checksum)->not->toBeNull()
        ->and($fresh->lines->pluck('status')->unique()->first())->toBe(Modules\ERP\Casts\PaymentRunLineStatus::Exported);
});
