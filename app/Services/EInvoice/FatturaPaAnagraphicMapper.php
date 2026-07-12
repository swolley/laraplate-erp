<?php

declare(strict_types=1);

namespace Modules\ERP\Services\EInvoice;

use Modules\ERP\Data\EInvoice\EInvoicePayload;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\Party;

final readonly class FatturaPaAnagraphicMapper
{
    public function toPayload(Invoice $invoice): EInvoicePayload
    {
        $invoice->loadMissing(['company', 'party', 'lines']);

        /** @var Company $company */
        $company = $invoice->company;
        /** @var Party $party */
        $party = $invoice->party;

        return new EInvoicePayload([
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
            'party_id' => $invoice->party_id,
            'provider_format' => 'fatturapa',
            'fatturapa' => [
                'dati_trasmissione' => $this->transmissionData($invoice, $company, $party),
                'cedente_prestatore' => $this->sellerData($company),
                'cessionario_committente' => $this->buyerData($party),
                'dati_generali_documento' => $this->documentData($invoice),
                'linee' => $invoice->lines
                    ->sortBy('line_no')
                    ->values()
                    ->map(fn (InvoiceLine $line): array => $this->lineData($line))
                    ->all(),
            ],
        ], 'application/vnd.laraplate.erp.einvoice.fatturapa+json');
    }

    /**
     * @return array<string, mixed>
     */
    private function transmissionData(Invoice $invoice, Company $company, Party $party): array
    {
        $recipient_code = $invoice->einvoice_recipient_code ?: $party->einvoice_recipient_code;
        $pec_email = $invoice->einvoice_pec_email ?: $party->einvoice_pec_email;

        return array_filter([
            'id_trasmittente' => [
                'id_paese' => $company->fiscal_country,
                'id_codice' => $company->tax_id,
            ],
            'progressivo_invio' => $this->safeTransmissionProgressive($invoice),
            'formato_trasmissione' => $invoice->einvoice_transmission_format,
            'codice_destinatario' => $recipient_code,
            'pec_destinatario' => $pec_email,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function sellerData(Company $company): array
    {
        return array_filter([
            'dati_anagrafici' => array_filter([
                'id_fiscale_iva' => [
                    'id_paese' => $company->fiscal_country,
                    'id_codice' => $company->tax_id,
                ],
                'anagrafica' => [
                    'denominazione' => $company->legal_name ?: $company->name,
                ],
                'regime_fiscale' => $company->fiscal_regime,
            ]),
            'sede' => $this->addressData(
                address: $company->legal_address_line,
                postal_code: $company->legal_postal_code,
                city: $company->legal_city,
                province: $company->legal_province,
                country: $company->legal_country,
            ),
            'iscrizione_rea' => $this->reaData($company),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    private function buyerData(Party $party): array
    {
        return [
            'dati_anagrafici' => array_filter([
                'id_fiscale_iva' => filled($party->vat_number)
                    ? [
                        'id_paese' => $party->fiscal_country,
                        'id_codice' => $party->vat_number,
                    ]
                    : null,
                'codice_fiscale' => $party->tax_id,
                'anagrafica' => [
                    'denominazione' => $party->name,
                ],
            ]),
            'sede' => $this->addressData(
                address: $party->address_line,
                postal_code: $party->postal_code,
                city: $party->city,
                province: $party->province,
                country: $party->country,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentData(Invoice $invoice): array
    {
        return array_filter([
            'tipo_documento' => $this->documentType($invoice),
            'divisa' => $invoice->currency,
            'data' => $invoice->posted_at?->toDateString(),
            'numero' => $invoice->reference ?? (string) $invoice->id,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function lineData(InvoiceLine $line): array
    {
        return [
            'numero_linea' => $line->line_no,
            'descrizione' => $line->description,
            'quantita' => $line->quantity,
            'prezzo_unitario' => $line->unit_price,
            'prezzo_totale' => number_format(
                round((float) $line->quantity * (float) $line->unit_price, 4),
                4,
                '.',
                '',
            ),
            'aliquota_iva' => $line->tax_rate,
            'codice_iva' => $line->tax_code,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function addressData(
        ?string $address,
        ?string $postal_code,
        ?string $city,
        ?string $province,
        ?string $country,
    ): array {
        return array_filter([
            'indirizzo' => $address,
            'cap' => $postal_code,
            'comune' => $city,
            'provincia' => $province,
            'nazione' => $country,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function reaData(Company $company): ?array
    {
        if (blank($company->rea_office) && blank($company->rea_number)) {
            return null;
        }

        return array_filter([
            'ufficio' => $company->rea_office,
            'numero_rea' => $company->rea_number,
            'capitale_sociale' => $company->share_capital,
            'socio_unico' => $company->sole_shareholder,
            'stato_liquidazione' => $company->liquidation_status,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function documentType(Invoice $invoice): string
    {
        return match ($invoice->invoice_type->value) {
            'credit_note' => 'TD04',
            'debit_note' => 'TD05',
            default => 'TD01',
        };
    }

    private function safeTransmissionProgressive(Invoice $invoice): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', $invoice->reference ?? (string) $invoice->id) ?: (string) $invoice->id;
    }
}
