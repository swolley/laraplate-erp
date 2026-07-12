<?php

declare(strict_types=1);

namespace Modules\ERP\Services\EInvoice;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DOMDocument;
use DOMElement;
use LibXMLError;
use Modules\ERP\Models\Invoice;
use RuntimeException;

final readonly class FatturaPaXmlBuilder
{
    private const NAMESPACE = 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2';

    public function __construct(
        private FatturaPaAnagraphicMapper $mapper,
    ) {}

    public function build(Invoice $invoice): string
    {
        $payload = $this->mapper->toPayload($invoice);
        $data = $payload->document['fatturapa'] ?? null;

        if (! is_array($data)) {
            throw new RuntimeException('FatturaPA payload data is missing.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $root = $document->createElementNS(self::NAMESPACE, 'p:FatturaElettronica');
        $root->setAttribute('versione', $this->stringValue($data['dati_trasmissione']['formato_trasmissione'] ?? 'FPR12'));
        $document->appendChild($root);

        $root->appendChild($this->header($document, $data));
        $root->appendChild($this->body($document, $data));

        $xml = $document->saveXML();

        if ($xml === false) {
            throw new RuntimeException('Unable to generate FatturaPA XML.');
        }

        return $xml;
    }

    public function validateXml(string $xml): void
    {
        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $loaded = $document->loadXML($xml);
        $valid = $loaded && $document->schemaValidate(module_path('ERP', 'resources/xsd/fatturapa/Schema_VFPR12_v1.2.3.xsd'));
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $valid) {
            $messages = array_map(
                static fn (LibXMLError $error): string => mb_trim($error->message),
                $errors,
            );

            throw new RuntimeException('Invalid FatturaPA XML: ' . implode('; ', array_filter($messages)));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function header(DOMDocument $document, array $data): DOMElement
    {
        $header = $document->createElement('FatturaElettronicaHeader');
        $header->appendChild($this->transmissionData($document, $this->arrayValue($data['dati_trasmissione'] ?? [])));
        $header->appendChild($this->seller($document, $this->arrayValue($data['cedente_prestatore'] ?? [])));
        $header->appendChild($this->buyer($document, $this->arrayValue($data['cessionario_committente'] ?? [])));

        return $header;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function transmissionData(DOMDocument $document, array $data): DOMElement
    {
        $element = $document->createElement('DatiTrasmissione');
        $element->appendChild($this->fiscalId($document, 'IdTrasmittente', $this->arrayValue($data['id_trasmittente'] ?? [])));
        $element->appendChild($this->textElement($document, 'ProgressivoInvio', $this->maxLength($this->stringValue($data['progressivo_invio'] ?? ''), 10)));
        $element->appendChild($this->textElement($document, 'FormatoTrasmissione', $this->stringValue($data['formato_trasmissione'] ?? 'FPR12')));
        $element->appendChild($this->textElement($document, 'CodiceDestinatario', $this->recipientCode($data)));

        $pec_email = $this->stringOrNull($data['pec_destinatario'] ?? null);

        if ($pec_email !== null) {
            $element->appendChild($this->textElement($document, 'PECDestinatario', $pec_email));
        }

        return $element;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function seller(DOMDocument $document, array $data): DOMElement
    {
        $element = $document->createElement('CedentePrestatore');
        $anagraphic = $this->arrayValue($data['dati_anagrafici'] ?? []);
        $element->appendChild($this->sellerAnagraphic($document, $anagraphic));
        $element->appendChild($this->address($document, 'Sede', $this->arrayValue($data['sede'] ?? [])));

        $rea_data = $this->arrayValue($data['iscrizione_rea'] ?? []);

        if ($rea_data !== []) {
            $element->appendChild($this->rea($document, $rea_data));
        }

        return $element;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sellerAnagraphic(DOMDocument $document, array $data): DOMElement
    {
        $element = $document->createElement('DatiAnagrafici');
        $element->appendChild($this->fiscalId($document, 'IdFiscaleIVA', $this->arrayValue($data['id_fiscale_iva'] ?? [])));
        $element->appendChild($this->anagraphicName($document, $this->arrayValue($data['anagrafica'] ?? [])));
        $element->appendChild($this->textElement($document, 'RegimeFiscale', $this->stringValue($data['regime_fiscale'] ?? 'RF01')));

        return $element;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buyer(DOMDocument $document, array $data): DOMElement
    {
        $element = $document->createElement('CessionarioCommittente');
        $anagraphic = $this->arrayValue($data['dati_anagrafici'] ?? []);
        $element->appendChild($this->buyerAnagraphic($document, $anagraphic));
        $element->appendChild($this->address($document, 'Sede', $this->arrayValue($data['sede'] ?? [])));

        return $element;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buyerAnagraphic(DOMDocument $document, array $data): DOMElement
    {
        $element = $document->createElement('DatiAnagrafici');
        $fiscal_id = $this->arrayValue($data['id_fiscale_iva'] ?? []);

        if ($fiscal_id !== []) {
            $element->appendChild($this->fiscalId($document, 'IdFiscaleIVA', $fiscal_id));
        }

        $tax_id = $this->stringOrNull($data['codice_fiscale'] ?? null);

        if ($tax_id !== null) {
            $element->appendChild($this->textElement($document, 'CodiceFiscale', $tax_id));
        }

        $element->appendChild($this->anagraphicName($document, $this->arrayValue($data['anagrafica'] ?? [])));

        return $element;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function body(DOMDocument $document, array $data): DOMElement
    {
        $body = $document->createElement('FatturaElettronicaBody');
        $body->appendChild($this->generalData($document, $this->arrayValue($data['dati_generali_documento'] ?? [])));
        $body->appendChild($this->goodsAndServices($document, $this->arrayValue($data['linee'] ?? [])));

        return $body;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function generalData(DOMDocument $document, array $data): DOMElement
    {
        $general_data = $document->createElement('DatiGenerali');
        $document_data = $document->createElement('DatiGeneraliDocumento');
        $document_data->appendChild($this->textElement($document, 'TipoDocumento', $this->stringValue($data['tipo_documento'] ?? 'TD01')));
        $document_data->appendChild($this->textElement($document, 'Divisa', $this->stringValue($data['divisa'] ?? 'EUR')));
        $document_data->appendChild($this->textElement($document, 'Data', $this->stringValue($data['data'] ?? now()->toDateString())));
        $document_data->appendChild($this->textElement($document, 'Numero', $this->maxLength($this->stringValue($data['numero'] ?? ''), 20)));
        $general_data->appendChild($document_data);

        return $general_data;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function goodsAndServices(DOMDocument $document, array $lines): DOMElement
    {
        $element = $document->createElement('DatiBeniServizi');
        $summaries = [];

        foreach ($lines as $line) {
            $element->appendChild($this->line($document, $line));
            $rate = $this->money($line['aliquota_iva'] ?? '0', 2);
            $summaries[$rate] ??= '0';
            $summaries[$rate] = $this->decimalAdd($summaries[$rate], $this->stringValue($line['prezzo_totale'] ?? '0'));
        }

        foreach ($summaries as $rate => $taxable_amount) {
            $element->appendChild($this->summary($document, $rate, $taxable_amount));
        }

        return $element;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function line(DOMDocument $document, array $data): DOMElement
    {
        $element = $document->createElement('DettaglioLinee');
        $element->appendChild($this->textElement($document, 'NumeroLinea', $this->stringValue($data['numero_linea'] ?? '1')));
        $element->appendChild($this->textElement($document, 'Descrizione', $this->stringValue($data['descrizione'] ?? 'Line')));
        $element->appendChild($this->textElement($document, 'Quantita', $this->money($data['quantita'] ?? '1', 4)));
        $element->appendChild($this->textElement($document, 'PrezzoUnitario', $this->money($data['prezzo_unitario'] ?? '0', 4)));
        $element->appendChild($this->textElement($document, 'PrezzoTotale', $this->money($data['prezzo_totale'] ?? '0', 4)));
        $element->appendChild($this->textElement($document, 'AliquotaIVA', $this->money($data['aliquota_iva'] ?? '0', 2)));

        return $element;
    }

    private function summary(DOMDocument $document, string $rate, string $taxable_amount): DOMElement
    {
        $tax = BigDecimal::of($taxable_amount)
            ->multipliedBy(BigDecimal::of($rate))
            ->dividedBy(BigDecimal::of('100'), 2, RoundingMode::HALF_UP);
        $element = $document->createElement('DatiRiepilogo');
        $element->appendChild($this->textElement($document, 'AliquotaIVA', $rate));
        $element->appendChild($this->textElement($document, 'ImponibileImporto', $this->money($taxable_amount, 2)));
        $element->appendChild($this->textElement($document, 'Imposta', $this->money((string) $tax, 2)));

        return $element;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fiscalId(DOMDocument $document, string $name, array $data): DOMElement
    {
        $element = $document->createElement($name);
        $element->appendChild($this->textElement($document, 'IdPaese', $this->stringValue($data['id_paese'] ?? 'IT')));
        $element->appendChild($this->textElement($document, 'IdCodice', $this->stringValue($data['id_codice'] ?? '')));

        return $element;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function anagraphicName(DOMDocument $document, array $data): DOMElement
    {
        $element = $document->createElement('Anagrafica');
        $element->appendChild($this->textElement($document, 'Denominazione', $this->maxLength($this->stringValue($data['denominazione'] ?? ''), 80)));

        return $element;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function address(DOMDocument $document, string $name, array $data): DOMElement
    {
        $element = $document->createElement($name);
        $element->appendChild($this->textElement($document, 'Indirizzo', $this->maxLength($this->stringValue($data['indirizzo'] ?? ''), 60)));
        $element->appendChild($this->textElement($document, 'CAP', $this->stringValue($data['cap'] ?? '00000')));
        $element->appendChild($this->textElement($document, 'Comune', $this->maxLength($this->stringValue($data['comune'] ?? ''), 60)));

        $province = $this->stringOrNull($data['provincia'] ?? null);

        if ($province !== null) {
            $element->appendChild($this->textElement($document, 'Provincia', $province));
        }

        $element->appendChild($this->textElement($document, 'Nazione', $this->stringValue($data['nazione'] ?? 'IT')));

        return $element;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function rea(DOMDocument $document, array $data): DOMElement
    {
        $element = $document->createElement('IscrizioneREA');
        $element->appendChild($this->textElement($document, 'Ufficio', $this->stringValue($data['ufficio'] ?? '')));
        $element->appendChild($this->textElement($document, 'NumeroREA', $this->stringValue($data['numero_rea'] ?? '')));

        $share_capital = $this->stringOrNull($data['capitale_sociale'] ?? null);

        if ($share_capital !== null) {
            $element->appendChild($this->textElement($document, 'CapitaleSociale', $this->money($share_capital, 2)));
        }

        $sole_shareholder = $this->stringOrNull($data['socio_unico'] ?? null);

        if ($sole_shareholder !== null) {
            $element->appendChild($this->textElement($document, 'SocioUnico', $this->booleanCode($sole_shareholder)));
        }

        $liquidation_status = $this->stringOrNull($data['stato_liquidazione'] ?? null);

        if ($liquidation_status !== null) {
            $element->appendChild($this->textElement($document, 'StatoLiquidazione', $liquidation_status));
        }

        return $element;
    }

    private function textElement(DOMDocument $document, string $name, string $value): DOMElement
    {
        $element = $document->createElement($name);
        $element->appendChild($document->createTextNode($value));

        return $element;
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    private function stringOrNull(mixed $value): ?string
    {
        $string = mb_trim($this->stringValue($value));

        return $string === '' ? null : $string;
    }

    private function recipientCode(array $data): string
    {
        $recipient_code = $this->stringOrNull($data['codice_destinatario'] ?? null);

        if ($recipient_code === null) {
            return '0000000';
        }

        return mb_strtoupper($recipient_code);
    }

    private function money(mixed $value, int $decimals): string
    {
        return (string) BigDecimal::of($this->stringValue($value))->toScale($decimals, RoundingMode::HALF_UP);
    }

    private function decimalAdd(string $left, string $right): string
    {
        return (string) BigDecimal::of($left)->plus(BigDecimal::of($right))->toScale(4, RoundingMode::HALF_UP);
    }

    private function maxLength(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }

    private function booleanCode(string $value): string
    {
        return match (mb_strtolower($value)) {
            '1', 'true', 'si' => 'SI',
            default => 'NO',
        };
    }
}
