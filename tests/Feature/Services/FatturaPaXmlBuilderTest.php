<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Party;
use Modules\ERP\Services\EInvoice\FatturaPaXmlBuilder;

uses(RefreshDatabase::class);

function createFatturaPaXmlCompany(): Company
{
    return Company::query()->create([
        'slug' => 'xml-company',
        'name' => 'XML Company',
        'legal_name' => 'XML Company SRL',
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

function createFatturaPaXmlParty(Company $company): Party
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

function createFatturaPaXmlInvoice(Company $company, Party $party): Invoice
{
    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'reference' => 'INV-2026-0001',
        'currency' => 'EUR',
        'posted_at' => now()->setDate(2026, 7, 12)->startOfDay(),
        'einvoice_transmission_format' => 'FPR12',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Consulting',
        'quantity' => '2.0000',
        'unit_price' => '50.0000',
        'tax_rate' => '22.0000',
    ]);

    return $invoice;
}

it('builds a schema-valid FatturaPA ordinary invoice XML document', function (): void {
    $company = createFatturaPaXmlCompany();
    $party = createFatturaPaXmlParty($company);
    $invoice = createFatturaPaXmlInvoice($company, $party);

    $xml = app(FatturaPaXmlBuilder::class)->build($invoice);

    $document = new DOMDocument;
    $document->preserveWhiteSpace = false;

    expect($document->loadXML($xml))->toBeTrue()
        ->and($document->documentElement?->localName)->toBe('FatturaElettronica')
        ->and($document->documentElement?->namespaceURI)->toBe('http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2')
        ->and($document->documentElement?->getAttribute('versione'))->toBe('FPR12')
        ->and($document->schemaValidate(module_path('ERP', 'resources/xsd/fatturapa/Schema_VFPR12_v1.2.3.xsd')))->toBeTrue();

    $this->assertXmlStringEqualsXmlFile(
        module_path('ERP', 'tests/Stubs/einvoice/golden-sale-invoice.xml'),
        $xml,
    );
});
