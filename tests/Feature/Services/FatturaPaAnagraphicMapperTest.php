<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Data\EInvoice\EInvoicePayload;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Party;
use Modules\ERP\Services\EInvoice\FatturaPaAnagraphicMapper;

uses(RefreshDatabase::class);

function createFatturaPaMapperCompany(): Company
{
    return Company::query()->create([
        'slug' => 'mapper-company',
        'name' => 'Mapper Company',
        'legal_name' => 'Mapper Company SRL',
        'tax_id' => '01234567890',
        'fiscal_country' => 'IT',
        'fiscal_regime' => 'RF01',
        'legal_address_line' => 'Via Roma 1',
        'legal_postal_code' => '00100',
        'legal_city' => 'Roma',
        'legal_province' => 'RM',
        'legal_country' => 'IT',
        'rea_office' => 'RM',
        'rea_number' => '123456',
        'share_capital' => '10000.00',
        'sole_shareholder' => true,
        'liquidation_status' => 'LN',
        'default_currency' => 'EUR',
    ]);
}

function createFatturaPaMapperParty(Company $company): Party
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

function createFatturaPaMapperInvoice(Company $company, Party $party): Invoice
{
    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'reference' => 'INV-2026-0001',
        'currency' => 'EUR',
        'posted_at' => now(),
        'einvoice_transmission_format' => 'FPR12',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Consulting',
        'quantity' => '2.0000',
        'unit_price' => '50.0000',
    ]);

    return $invoice;
}

it('maps company party and invoice into a fatturapa shaped payload', function (): void {
    $company = createFatturaPaMapperCompany();
    $party = createFatturaPaMapperParty($company);
    $invoice = createFatturaPaMapperInvoice($company, $party);

    $payload = app(FatturaPaAnagraphicMapper::class)->toPayload($invoice);

    expect($payload)->toBeInstanceOf(EInvoicePayload::class)
        ->and($payload->mimeType)->toBe('application/vnd.laraplate.erp.einvoice.fatturapa+json')
        ->and($payload->document['invoice_id'])->toBe($invoice->id)
        ->and($payload->document['fatturapa']['dati_trasmissione']['id_trasmittente']['id_paese'])->toBe('IT')
        ->and($payload->document['fatturapa']['dati_trasmissione']['id_trasmittente']['id_codice'])->toBe('01234567890')
        ->and($payload->document['fatturapa']['dati_trasmissione']['formato_trasmissione'])->toBe('FPR12')
        ->and($payload->document['fatturapa']['dati_trasmissione']['codice_destinatario'])->toBe('ABCDEFG')
        ->and($payload->document['fatturapa']['cedente_prestatore']['dati_anagrafici']['anagrafica']['denominazione'])->toBe('Mapper Company SRL')
        ->and($payload->document['fatturapa']['cedente_prestatore']['dati_anagrafici']['regime_fiscale'])->toBe('RF01')
        ->and($payload->document['fatturapa']['cedente_prestatore']['sede']['indirizzo'])->toBe('Via Roma 1')
        ->and($payload->document['fatturapa']['cessionario_committente']['dati_anagrafici']['id_fiscale_iva']['id_codice'])->toBe('09876543210')
        ->and($payload->document['fatturapa']['cessionario_committente']['dati_anagrafici']['codice_fiscale'])->toBe('RSSMRA80A01H501U')
        ->and($payload->document['fatturapa']['cessionario_committente']['sede']['comune'])->toBe('Milano')
        ->and($payload->document['fatturapa']['dati_generali_documento']['numero'])->toBe('INV-2026-0001')
        ->and($payload->document['fatturapa']['linee'][0]['numero_linea'])->toBe(1)
        ->and($payload->document['fatturapa']['linee'][0]['prezzo_totale'])->toBe('100.0000');
});
