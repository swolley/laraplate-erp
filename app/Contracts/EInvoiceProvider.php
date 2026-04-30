<?php

declare(strict_types=1);

namespace Modules\ERP\Contracts;

use Modules\ERP\Data\EInvoice\EInvoicePayload;
use Modules\ERP\Data\EInvoice\EInvoiceRemoteStatus;
use Modules\ERP\Data\EInvoice\EInvoiceSubmissionResult;
use Modules\ERP\Models\Invoice;

/**
 * Contract for national or third-party e-invoice gateways (e.g. SDI bindings live in separate packages).
 */
interface EInvoiceProvider
{
    /**
     * Stable provider identifier stored on {@see \Modules\ERP\Models\EInvoiceSubmission::$provider_code}.
     */
    public function code(): string;

    /**
     * Build a neutral payload from an invoice (no I/O).
     */
    public function prepare(Invoice $invoice): EInvoicePayload;

    /**
     * Submit the payload to the gateway (network / queue side effects allowed in implementations).
     */
    public function submit(EInvoicePayload $payload): EInvoiceSubmissionResult;

    /**
     * Poll or resolve remote status for a previously returned {@see EInvoiceSubmissionResult::$externalId}.
     */
    public function remoteStatus(string $externalId): EInvoiceRemoteStatus;
}
