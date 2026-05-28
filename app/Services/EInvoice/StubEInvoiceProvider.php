<?php

declare(strict_types=1);

namespace Modules\ERP\Services\EInvoice;

use Modules\ERP\Contracts\EInvoiceProvider;
use Modules\ERP\Data\EInvoice\EInvoicePayload;
use Modules\ERP\Data\EInvoice\EInvoiceRemoteStatus;
use Modules\ERP\Data\EInvoice\EInvoiceSubmissionResult;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;
use Override;

final readonly class StubEInvoiceProvider implements EInvoiceProvider
{
    #[Override]
    public function code(): string
    {
        return 'stub';
    }

    #[Override]
    public function prepare(Invoice $invoice): EInvoicePayload
    {
        $invoice->loadMissing('lines');

        return new EInvoicePayload([
            'invoice_id' => (int) $invoice->id,
            'company_id' => (int) $invoice->company_id,
            'direction' => $invoice->direction->value,
            'invoice_type' => $invoice->invoice_type->value,
            'reference' => $invoice->reference,
            'currency' => $invoice->currency,
            'posted_at' => $invoice->posted_at?->toISOString(),
            'lines' => $invoice->lines
                ->map(static fn (InvoiceLine $line): array => [
                    'line_no' => (int) $line->line_no,
                    'description' => $line->description,
                    'quantity' => (string) $line->quantity,
                    'unit_price' => (string) $line->unit_price,
                    'line_total' => number_format(
                        round((float) $line->quantity * (float) $line->unit_price, 4),
                        4,
                        '.',
                        '',
                    ),
                    'tax_code' => $line->tax_code,
                    'tax_rate' => $line->tax_rate !== null ? (string) $line->tax_rate : null,
                ])
                ->values()
                ->all(),
        ], 'application/vnd.laraplate.erp.einvoice.stub+json');
    }

    #[Override]
    public function submit(EInvoicePayload $payload): EInvoiceSubmissionResult
    {
        $invoice_id = (int) ($payload->document['invoice_id'] ?? 0);
        $external_id = $invoice_id > 0 ? 'STUB-' . $invoice_id : 'STUB-UNKNOWN';

        return new EInvoiceSubmissionResult(
            externalId: $external_id,
            success: true,
            message: 'Stub e-invoice accepted for local workflow testing.',
            raw: [
                'provider' => $this->code(),
                'external_id' => $external_id,
                'document' => $payload->document,
            ],
        );
    }

    #[Override]
    public function remoteStatus(string $externalId): EInvoiceRemoteStatus
    {
        if (str_starts_with($externalId, 'STUB-')) {
            return EInvoiceRemoteStatus::Accepted;
        }

        return EInvoiceRemoteStatus::Unknown;
    }
}
