<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\EInvoiceSubmissionStatus;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\EInvoiceSubmission;
use Modules\ERP\Models\Invoice;

uses(RefreshDatabase::class);

it('creates e_invoice_submissions table with expected columns', function (): void {
    expect(Schema::hasTable('e_invoice_submissions'))->toBeTrue()
        ->and(Schema::hasColumns('e_invoice_submissions', [
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
        'currency' => 'EUR',
    ]);

    $submission = EInvoiceSubmission::query()->create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'provider_code' => 'noop',
        'status' => EInvoiceSubmissionStatus::DRAFT,
    ]);

    expect($submission->invoice)->toBeInstanceOf(Invoice::class)
        ->and($submission->invoice_id)->toBe($invoice->id)
        ->and($invoice->eInvoiceSubmissions)->toHaveCount(1);
});
