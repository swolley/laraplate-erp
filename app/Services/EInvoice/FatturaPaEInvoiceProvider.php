<?php

declare(strict_types=1);

namespace Modules\ERP\Services\EInvoice;

use Modules\ERP\Contracts\EInvoiceProvider;
use Modules\ERP\Data\EInvoice\EInvoicePayload;
use Modules\ERP\Data\EInvoice\EInvoiceRemoteStatus;
use Modules\ERP\Data\EInvoice\EInvoiceSubmissionResult;
use Modules\ERP\Models\Invoice;
use Override;

final readonly class FatturaPaEInvoiceProvider implements EInvoiceProvider
{
    public function __construct(
        private FatturaPaXmlBuilder $builder,
    ) {}

    #[Override]
    public function code(): string
    {
        return 'fatturapa';
    }

    #[Override]
    public function prepare(Invoice $invoice): EInvoicePayload
    {
        $xml = $this->builder->build($invoice);
        $this->validateXml($xml);

        return new EInvoicePayload([
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
            'provider_format' => 'fatturapa',
            'xml' => $xml,
        ], 'application/vnd.fatturapa+xml');
    }

    #[Override]
    public function submit(EInvoicePayload $payload): EInvoiceSubmissionResult
    {
        $xml = $payload->document['xml'] ?? null;

        if (is_string($xml)) {
            $this->validateXml($xml);
        }

        $invoice_id = $this->invoiceIdFromPayload($payload);
        $external_id = $invoice_id > 0 ? 'FATTURAPA-' . $invoice_id : 'FATTURAPA-UNKNOWN';

        return new EInvoiceSubmissionResult(
            externalId: $external_id,
            success: true,
            message: 'FatturaPA XML generated and validated for provider handoff.',
            raw: [
                'provider' => $this->code(),
                'external_id' => $external_id,
                'mime_type' => $payload->mimeType,
                'schema_validated' => is_string($xml),
                'xml' => $xml,
            ],
        );
    }

    #[Override]
    public function validateXml(string $xml): void
    {
        $this->builder->validateXml($xml);
    }

    #[Override]
    public function remoteStatus(string $externalId): EInvoiceRemoteStatus
    {
        if (str_starts_with($externalId, 'FATTURAPA-')) {
            return EInvoiceRemoteStatus::Processing;
        }

        return EInvoiceRemoteStatus::Unknown;
    }

    private function invoiceIdFromPayload(EInvoicePayload $payload): int
    {
        $raw_id = $payload->document['invoice_id'] ?? 0;

        if (is_int($raw_id)) {
            return $raw_id;
        }

        if (is_string($raw_id) && ctype_digit($raw_id)) {
            return (int) $raw_id;
        }

        return 0;
    }
}
