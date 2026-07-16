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
    config()->set('erp.einvoice.aruba.base_url', 'https://aruba.test');
    config()->set('erp.einvoice.aruba.upload_path', '/services/invoice/upload');
    config()->set('erp.einvoice.aruba.notifications_path', '/api/v2/invoices-out/notifications');
    config()->set('erp.einvoice.aruba.token', 'secret-token');
}

it('resolves the Aruba e-invoice provider when configured', function (): void {
    configureArubaEInvoiceProvider();

    expect(app(EInvoiceProvider::class))->toBeInstanceOf(ArubaEInvoiceProvider::class);
});

it('submits FatturaPA XML to the configured Aruba endpoint', function (): void {
    configureArubaEInvoiceProvider();

    Http::fake([
        'https://aruba.test/services/invoice/upload' => Http::response([
            'errorCode' => '0000',
            'errorDescription' => 'Operazione effettuata - request-123',
            'uploadFileName' => 'IT01234567890_INV-ARUBA-001.xml.p7m',
        ]),
    ]);

    $company = createArubaEInvoiceCompany();
    $party = createArubaEInvoiceParty($company);
    $invoice = createArubaEInvoiceInvoice($company, $party);
    $provider = app(EInvoiceProvider::class);

    $payload = $provider->prepare($invoice);
    $result = $provider->submit($payload);

    expect($result->success)->toBeTrue()
        ->and($result->externalId)->toBe('IT01234567890_INV-ARUBA-001.xml.p7m')
        ->and($result->message)->toBe('Operazione effettuata - request-123')
        ->and($result->raw['provider'])->toBe('aruba')
        ->and($result->raw['upload_file_name'])->toBe('IT01234567890_INV-ARUBA-001.xml.p7m');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer secret-token')
        && $request->url() === 'https://aruba.test/services/invoice/upload'
        && base64_decode($request['dataFile'], true) !== false
        && $request['senderPIVA'] === 'IT01234567890'
        && $request['skipExtraSchema'] === false
        && $request['dryRun'] === false);
});

it('maps Aruba remote statuses through the configured endpoint', function (): void {
    configureArubaEInvoiceProvider();

    Http::fake([
        'https://aruba.test/api/v2/invoices-out/notifications?filename=IT01234567890_INV-ARUBA-001.xml.p7m' => Http::response([
            'count' => 1,
            'notifications' => [[
                'docType' => 'RC',
                'filename' => 'IT01234567890_INV-ARUBA-001_RC_001.xml',
                'invoiceId' => '42',
                'result' => 'EC01',
            ]],
            'pddAvailable' => true,
        ]),
    ]);

    expect(app(EInvoiceProvider::class)->remoteStatus('IT01234567890_INV-ARUBA-001.xml.p7m'))->toBe(EInvoiceRemoteStatus::Accepted);
});

it('refreshes Aruba submissions through the submission service', function (): void {
    configureArubaEInvoiceProvider();

    Http::fake([
        'https://aruba.test/api/v2/invoices-out/notifications?filename=IT01234567890_INV-ARUBA-001.xml.p7m' => Http::response([
            'count' => 1,
            'notifications' => [[
                'docType' => 'NS',
                'filename' => 'IT01234567890_INV-ARUBA-001_NS_001.xml',
                'invoiceId' => '42',
                'result' => null,
            ]],
            'pddAvailable' => false,
        ]),
    ]);

    $company = createArubaEInvoiceCompany();
    $party = createArubaEInvoiceParty($company);
    $invoice = createArubaEInvoiceInvoice($company, $party);
    $submission = EInvoiceSubmission::query()->create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'provider_code' => 'aruba',
        'external_id' => 'IT01234567890_INV-ARUBA-001.xml.p7m',
        'status' => Modules\ERP\Casts\EInvoiceSubmissionStatus::Submitted,
    ]);

    $refreshed = app(EInvoiceSubmissionService::class)->refresh($submission);

    expect($refreshed->status)->toBe(Modules\ERP\Casts\EInvoiceSubmissionStatus::Rejected)
        ->and($refreshed->response_payload['last_remote_status'])->toBe('rejected')
        ->and($refreshed->response_payload['provider_poll']['notifications'][0]['docType'])->toBe('NS')
        ->and($refreshed->response_payload['conservation']['pdd_available'])->toBeFalse();
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

it('applies Aruba callback payloads to stored submissions', function (): void {
    configureArubaEInvoiceProvider();

    $company = createArubaEInvoiceCompany();
    $party = createArubaEInvoiceParty($company);
    $invoice = createArubaEInvoiceInvoice($company, $party);
    $submission = EInvoiceSubmission::query()->create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'provider_code' => 'aruba',
        'external_id' => 'IT01234567890_INV-ARUBA-001.xml.p7m',
        'status' => Modules\ERP\Casts\EInvoiceSubmissionStatus::Submitted,
    ]);

    $updated = app(EInvoiceSubmissionService::class)->applyProviderCallback('aruba', [
        'invoiceFileName' => 'IT01234567890_INV-ARUBA-001.xml.p7m',
        'sdiIdentification' => '123456789',
        'notifyType' => 'RC',
        'notifyFileName' => 'IT01234567890_INV-ARUBA-001_RC_001.xml',
        'notificationDate' => '2026-07-16T10:00:00+02:00',
        'result' => 'EC01',
    ]);

    expect($updated->id)->toBe($submission->id)
        ->and($updated->status)->toBe(Modules\ERP\Casts\EInvoiceSubmissionStatus::Accepted)
        ->and($updated->response_payload['callbacks'][0]['notifyType'])->toBe('RC')
        ->and($updated->response_payload['aruba']['sdi_identification'])->toBe('123456789');
});


it('accepts signed Aruba callbacks through the module api route', function (): void {
    configureArubaEInvoiceProvider();
    config()->set('erp.einvoice.aruba.callback_api_key', 'callback-secret');

    $company = createArubaEInvoiceCompany();
    $party = createArubaEInvoiceParty($company);
    $invoice = createArubaEInvoiceInvoice($company, $party);
    $submission = EInvoiceSubmission::query()->create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'provider_code' => 'aruba',
        'external_id' => 'IT01234567890_INV-ARUBA-001.xml.p7m',
        'status' => Modules\ERP\Casts\EInvoiceSubmissionStatus::Submitted,
    ]);

    $this->withHeader('Authorization', 'Bearer callback-secret')
        ->postJson('/api/v1/erp/einvoice/aruba/callbacks', [
            'invoiceFileName' => 'IT01234567890_INV-ARUBA-001.xml.p7m',
            'notifyType' => 'RC',
            'notifyFileName' => 'IT01234567890_INV-ARUBA-001_RC_001.xml',
            'result' => 'EC01',
        ])
        ->assertOk()
        ->assertJsonPath('data.id', $submission->id)
        ->assertJsonPath('data.status', 'accepted');

    expect($submission->refresh()->response_payload['callbacks'][0]['notifyType'])->toBe('RC');
});

it('rejects Aruba callbacks when the configured api key does not match', function (): void {
    configureArubaEInvoiceProvider();
    config()->set('erp.einvoice.aruba.callback_api_key', 'callback-secret');

    $this->withHeader('Authorization', 'Bearer wrong')
        ->postJson('/api/v1/erp/einvoice/aruba/callbacks', [])
        ->assertUnauthorized();
});

it('refreshes open Aruba submissions through the polling command', function (): void {
    configureArubaEInvoiceProvider();

    Http::fake([
        'https://aruba.test/api/v2/invoices-out/notifications?filename=IT01234567890_INV-ARUBA-001.xml.p7m' => Http::response([
            'count' => 1,
            'notifications' => [[
                'docType' => 'RC',
                'filename' => 'IT01234567890_INV-ARUBA-001_RC_001.xml',
                'invoiceId' => '42',
                'result' => 'EC01',
            ]],
            'pddAvailable' => true,
        ]),
    ]);

    $company = createArubaEInvoiceCompany();
    $party = createArubaEInvoiceParty($company);
    $invoice = createArubaEInvoiceInvoice($company, $party);
    $submission = EInvoiceSubmission::query()->create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'provider_code' => 'aruba',
        'external_id' => 'IT01234567890_INV-ARUBA-001.xml.p7m',
        'status' => Modules\ERP\Casts\EInvoiceSubmissionStatus::Submitted,
    ]);

    $this->artisan('erp:einvoice:refresh-statuses', [
        '--company' => (string) $company->id,
        '--limit' => '10',
    ])->assertSuccessful();

    expect($submission->refresh()->status)->toBe(Modules\ERP\Casts\EInvoiceSubmissionStatus::Accepted)
        ->and($submission->response_payload['conservation']['pdd_available'])->toBeTrue();
});
