<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\EInvoiceSubmissionStatus;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\EInvoiceSubmission;
use Modules\ERP\Models\Invoice;

uses(RefreshDatabase::class);

it('creates e_invoice_submissions table with expected columns', function (): void {
    expect(Schema::hasTable(ERPTables::EInvoiceSubmissions->value))->toBeTrue()
        ->and(Schema::hasColumns(ERPTables::EInvoiceSubmissions->value, [
            'company_id',
            'invoice_id',
            'provider_code',
            'external_id',
            'status',
            'last_payload_path',
            'submitted_at',
            'response_payload',
        ]))->toBeTrue();
});

it('creates fatturapa readiness columns on company party and invoice records', function (): void {
    expect(Schema::hasColumns(ERPTables::Companies->value, [
        'fiscal_regime',
        'legal_address_line',
        'legal_postal_code',
        'legal_city',
        'legal_country',
        'rea_office',
        'rea_number',
        'share_capital',
        'sole_shareholder',
        'liquidation_status',
    ]))->toBeTrue()
        ->and(Schema::hasColumns(ERPTables::Parties->value, [
            'tax_id',
            'vat_number',
            'fiscal_country',
            'address_line',
            'postal_code',
            'city',
            'country',
            'einvoice_recipient_code',
            'einvoice_pec_email',
        ]))->toBeTrue()
        ->and(Schema::hasColumns(ERPTables::Invoices->value, [
            'einvoice_transmission_format',
            'einvoice_recipient_code',
            'einvoice_pec_email',
        ]))->toBeTrue();
});

it('persists an e-invoice submission linked to an invoice', function (): void {
    $company = Company::query()->create([
        'slug' => 'acme',
        'name' => 'Acme',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);

    $submission = EInvoiceSubmission::query()->create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'provider_code' => 'noop',
        'status' => EInvoiceSubmissionStatus::Draft,
    ]);

    expect($submission->invoice)->toBeInstanceOf(Invoice::class)
        ->and($submission->invoice_id)->toBe($invoice->id)
        ->and($invoice->eInvoiceSubmissions)->toHaveCount(1);
});
