<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\EInvoiceSubmissionStatus;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Contracts\EInvoiceProvider;
use Modules\ERP\Data\EInvoice\EInvoicePayload;
use Modules\ERP\Data\EInvoice\EInvoiceRemoteStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\EInvoiceSubmission;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Party;
use Modules\ERP\Services\EInvoice\EInvoiceSubmissionService;
use Modules\ERP\Services\EInvoice\FatturaPaEInvoiceProvider;
use Modules\ERP\Services\EInvoice\StubEInvoiceProvider;

uses(RefreshDatabase::class);

function createEInvoiceCompany(string $slug = 'einvoice', bool $fatturapa_ready = true): Company
{
    $data = [
        'slug' => $slug,
        'name' => ucfirst($slug),
        'legal_name' => ucfirst($slug) . ' SRL',
        'tax_id' => '01234567890',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ];

    if ($fatturapa_ready) {
        $data = array_merge($data, [
            'fiscal_regime' => 'RF01',
            'legal_address_line' => 'Via Roma 1',
            'legal_postal_code' => '00100',
            'legal_city' => 'Roma',
            'legal_province' => 'RM',
            'legal_country' => 'IT',
        ]);
    }

    return Company::query()->create($data);
}

function createEInvoiceParty(Company $company, bool $fatturapa_ready = true): Party
{
    $data = [
        'company_id' => $company->id,
        'name' => 'Customer SRL',
        'is_customer' => true,
        'is_supplier' => false,
    ];

    if ($fatturapa_ready) {
        $data = array_merge($data, [
            'vat_number' => '09876543210',
            'fiscal_country' => 'IT',
            'address_line' => 'Via Milano 10',
            'postal_code' => '20100',
            'city' => 'Milano',
            'province' => 'MI',
            'country' => 'IT',
            'einvoice_recipient_code' => 'ABCDEFG',
        ]);
    }

    return Party::query()->create($data);
}

function createEInvoiceInvoice(
    Company $company,
    InvoiceDirection $direction = InvoiceDirection::Sale,
    bool $posted = true,
    bool $fatturapa_ready = true,
): Invoice {
    $party = createEInvoiceParty($company, $fatturapa_ready);
    $data = [
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => $direction,
        'invoice_type' => InvoiceType::Invoice->value,
        'reference' => $posted ? 'INV-001' : null,
        'currency' => 'EUR',
        'posted_at' => $posted ? now() : null,
    ];

    if ($fatturapa_ready) {
        $data = array_merge($data, [
            'einvoice_transmission_format' => 'FPR12',
            'einvoice_recipient_code' => 'ABCDEFG',
        ]);
    }

    $invoice = Invoice::query()->create($data);

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Service',
        'quantity' => '2.0000',
        'unit_price' => '50.0000',
    ]);

    return $invoice;
}

it('resolves the stub e-invoice provider by default', function (): void {
    expect(app(EInvoiceProvider::class))->toBeInstanceOf(StubEInvoiceProvider::class);
});

it('resolves the FatturaPA provider when configured', function (): void {
    config()->set('erp.einvoice.driver', 'fatturapa');

    expect(app(EInvoiceProvider::class))->toBeInstanceOf(FatturaPaEInvoiceProvider::class);
});

it('prepares a neutral payload without requiring fatturapa fields', function (): void {
    $company = createEInvoiceCompany(fatturapa_ready: false);
    $invoice = createEInvoiceInvoice($company, fatturapa_ready: false);

    $payload = app(EInvoiceProvider::class)->prepare($invoice);

    expect($payload)->toBeInstanceOf(EInvoicePayload::class)
        ->and($payload->document['invoice_id'])->toBe($invoice->id)
        ->and($payload->document['company_id'])->toBe($company->id)
        ->and($payload->document['direction'])->toBe(InvoiceDirection::Sale->value)
        ->and($payload->document['lines'])->toHaveCount(1);
});

it('submits stub payloads with a deterministic external id', function (): void {
    $company = createEInvoiceCompany();
    $invoice = createEInvoiceInvoice($company);
    $payload = app(EInvoiceProvider::class)->prepare($invoice);

    $result = app(EInvoiceProvider::class)->submit($payload);

    expect($result->success)->toBeTrue()
        ->and($result->externalId)->toBe('STUB-' . $invoice->id)
        ->and($result->raw['provider'])->toBe('stub');
});

it('maps stub remote status for known external ids', function (): void {
    expect(app(EInvoiceProvider::class)->remoteStatus('STUB-1'))->toBe(EInvoiceRemoteStatus::Accepted)
        ->and(app(EInvoiceProvider::class)->remoteStatus('OTHER-1'))->toBe(EInvoiceRemoteStatus::Unknown);
});

it('resolves stub external ids from numeric-string payload invoice ids', function (): void {
    $provider = new StubEInvoiceProvider;
    $payload = new EInvoicePayload(['invoice_id' => '42'], 'application/json');

    expect($provider->submit($payload)->externalId)->toBe('STUB-42');
});

it('falls back to stub-unknown when payload invoice id is invalid', function (): void {
    $provider = new StubEInvoiceProvider;
    $payload = new EInvoicePayload(['invoice_id' => 'not-numeric'], 'application/json');

    expect($provider->submit($payload)->externalId)->toBe('STUB-UNKNOWN');
});

it('persists an e-invoice submission for a posted sale invoice', function (): void {
    $company = createEInvoiceCompany();
    $invoice = createEInvoiceInvoice($company);

    $submission = app(EInvoiceSubmissionService::class)->submit($invoice);

    expect($submission)->toBeInstanceOf(EInvoiceSubmission::class)
        ->and($submission->company_id)->toBe($company->id)
        ->and($submission->invoice_id)->toBe($invoice->id)
        ->and($submission->provider_code)->toBe('stub')
        ->and($submission->external_id)->toBe('STUB-' . $invoice->id)
        ->and($submission->status)->toBe(EInvoiceSubmissionStatus::Submitted);
});

it('submits a schema-valid FatturaPA XML payload when the driver is fatturapa', function (): void {
    config()->set('erp.einvoice.driver', 'fatturapa');
    app()->forgetInstance(EInvoiceSubmissionService::class);

    $company = createEInvoiceCompany('einvoice-fatturapa');
    $invoice = createEInvoiceInvoice($company);

    $submission = app(EInvoiceSubmissionService::class)->submit($invoice);

    expect($submission->provider_code)->toBe('fatturapa')
        ->and($submission->external_id)->toBe('FATTURAPA-' . $invoice->id)
        ->and($submission->response_payload['raw']['provider'])->toBe('fatturapa')
        ->and($submission->response_payload['raw']['xml'])->toContain('FatturaElettronica')
        ->and($submission->response_payload['raw']['schema_validated'])->toBeTrue();
});

it('rejects unposted or purchase invoices', function (): void {
    $company = createEInvoiceCompany('einvoice-invalid');
    $unposted = createEInvoiceInvoice($company, posted: false);
    $purchase = createEInvoiceInvoice($company, InvoiceDirection::Purchase);

    expect(fn () => app(EInvoiceSubmissionService::class)->submit($unposted))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(EInvoiceSubmissionService::class)->submit($purchase))
        ->toThrow(ValidationException::class);
});

it('rejects sale e-invoice submission when mandatory fatturapa readiness data is missing', function (): void {
    $company = createEInvoiceCompany('einvoice-missing-sdi', fatturapa_ready: false);
    $invoice = createEInvoiceInvoice($company);

    expect(fn () => app(EInvoiceSubmissionService::class)->submit($invoice))
        ->toThrow(ValidationException::class);
});

it('refreshes a submitted stub e-invoice to accepted', function (): void {
    $company = createEInvoiceCompany();
    $invoice = createEInvoiceInvoice($company);
    $submission = app(EInvoiceSubmissionService::class)->submit($invoice);

    $refreshed = app(EInvoiceSubmissionService::class)->refresh($submission);

    expect($refreshed->status)->toBe(EInvoiceSubmissionStatus::Accepted);
});
