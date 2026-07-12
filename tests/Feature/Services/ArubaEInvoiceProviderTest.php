<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Contracts\EInvoiceProvider;
use Modules\ERP\Data\EInvoice\EInvoiceRemoteStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\EInvoiceSubmission;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Party;
use Modules\ERP\Services\EInvoice\ArubaEInvoiceProvider;
use Modules\ERP\Services\EInvoice\EInvoiceSubmissionService;

uses(RefreshDatabase::class);

function createArubaEInvoiceCompany(): Company
{
    return Company::query()->create([
        'slug' => 'aruba-einvoice',
        'name' => 'Aruba EInvoice',
        'legal_name' => 'Aruba EInvoice SRL',
        'tax_id' => '01234567890',
        'fiscal_country' => 'IT',
        'fiscal_regime' => 'RF01',
        'legal_address_line' => 'Via Roma 1',
        'legal_postal_code' => '00100',
        'legal_city' => 'Roma',
        'legal_province' => 'RM',
        'legal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
}

function createArubaEInvoiceParty(Company $company): Party
{
    return Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Customer SRL',
        'tax_id' => 'RSSMRA80A01H501U',
        'vat_number' => '09876543210',
        'fiscal_country' => 'IT',
        'address_line' => 'Via Milano 10',
        'postal_code' => '20100',
        'city' => 'Milano',
        'province' => 'MI',
        'country' => 'IT',
        'einvoice_recipient_code' => 'ABCDEFG',
        'is_customer' => true,
        'is_supplier' => false,
    ]);
}

function createArubaEInvoiceInvoice(Company $company, Party $party): Invoice
{
    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'reference' => 'INV-ARUBA-001',
        'currency' => 'EUR',
        'posted_at' => now()->setDate(2026, 7, 12)->startOfDay(),
        'einvoice_transmission_format' => 'FPR12',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Service',
        'quantity' => '2.0000',
        'unit_price' => '50.0000',
        'tax_rate' => '22.0000',
    ]);

    return $invoice;
}

function configureArubaEInvoiceProvider(): void
{
    config()->set('erp.einvoice.driver', 'aruba');
    config()->set('erp.einvoice.aruba.base_url', 'https://aruba.test/api');
    config()->set('erp.einvoice.aruba.submit_path', '/einvoices');
    config()->set('erp.einvoice.aruba.status_path', '/einvoices/{external_id}');
    config()->set('erp.einvoice.aruba.token', 'secret-token');
}

it('resolves the Aruba e-invoice provider when configured', function (): void {
    configureArubaEInvoiceProvider();

    expect(app(EInvoiceProvider::class))->toBeInstanceOf(ArubaEInvoiceProvider::class);
});

it('submits FatturaPA XML to the configured Aruba endpoint', function (): void {
    configureArubaEInvoiceProvider();

    Http::fake([
        'https://aruba.test/api/einvoices' => Http::response([
            'id' => 'ARB-123',
            'status' => 'received',
            'message' => 'Queued',
        ], 202),
    ]);

    $company = createArubaEInvoiceCompany();
    $party = createArubaEInvoiceParty($company);
    $invoice = createArubaEInvoiceInvoice($company, $party);
    $provider = app(EInvoiceProvider::class);

    $payload = $provider->prepare($invoice);
    $result = $provider->submit($payload);

    expect($result->success)->toBeTrue()
        ->and($result->externalId)->toBe('ARB-123')
        ->and($result->message)->toBe('Queued')
        ->and($result->raw['provider'])->toBe('aruba')
        ->and($result->raw['status'])->toBe('received');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer secret-token')
        && $request->url() === 'https://aruba.test/api/einvoices'
        && $request['file_name'] === 'IT01234567890_INV-ARUBA-001.xml'
        && base64_decode($request['xml_base64'], true) !== false);
});

it('maps Aruba remote statuses through the configured endpoint', function (): void {
    configureArubaEInvoiceProvider();

    Http::fake([
        'https://aruba.test/api/einvoices/ARB-123' => Http::response([
            'status' => 'accepted',
        ]),
    ]);

    expect(app(EInvoiceProvider::class)->remoteStatus('ARB-123'))->toBe(EInvoiceRemoteStatus::Accepted);
});

it('refreshes Aruba submissions through the submission service', function (): void {
    configureArubaEInvoiceProvider();

    Http::fake([
        'https://aruba.test/api/einvoices/ARB-123' => Http::response([
            'status' => 'rejected',
        ]),
    ]);

    $company = createArubaEInvoiceCompany();
    $party = createArubaEInvoiceParty($company);
    $invoice = createArubaEInvoiceInvoice($company, $party);
    $submission = EInvoiceSubmission::query()->create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'provider_code' => 'aruba',
        'external_id' => 'ARB-123',
        'status' => Modules\ERP\Casts\EInvoiceSubmissionStatus::Submitted,
    ]);

    $refreshed = app(EInvoiceSubmissionService::class)->refresh($submission);

    expect($refreshed->status)->toBe(Modules\ERP\Casts\EInvoiceSubmissionStatus::Rejected)
        ->and($refreshed->response_payload['last_remote_status'])->toBe('rejected');
});

it('requires Aruba base url and token before HTTP operations', function (): void {
    config()->set('erp.einvoice.driver', 'aruba');
    config()->set('erp.einvoice.aruba.base_url', null);
    config()->set('erp.einvoice.aruba.token', null);

    $company = createArubaEInvoiceCompany();
    $party = createArubaEInvoiceParty($company);
    $invoice = createArubaEInvoiceInvoice($company, $party);
    $provider = app(EInvoiceProvider::class);

    expect(fn () => $provider->submit($provider->prepare($invoice)))
        ->toThrow(RuntimeException::class, 'Aruba e-invoice base URL is not configured.');
});
