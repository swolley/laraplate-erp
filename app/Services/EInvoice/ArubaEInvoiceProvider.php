<?php

declare(strict_types=1);

namespace Modules\ERP\Services\EInvoice;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Modules\ERP\Contracts\EInvoiceProvider;
use Modules\ERP\Data\EInvoice\EInvoicePayload;
use Modules\ERP\Data\EInvoice\EInvoiceRemoteStatus;
use Modules\ERP\Data\EInvoice\EInvoiceSubmissionResult;
use Modules\ERP\Models\Invoice;
use Override;
use RuntimeException;

final readonly class ArubaEInvoiceProvider implements EInvoiceProvider
{
    public function __construct(
        private FatturaPaXmlBuilder $builder,
    ) {}

    #[Override]
    public function code(): string
    {
        return 'aruba';
    }

    #[Override]
    public function prepare(Invoice $invoice): EInvoicePayload
    {
        $invoice->loadMissing('company');

        $xml = $this->builder->build($invoice);
        $this->validateXml($xml);

        return new EInvoicePayload([
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
            'provider_format' => 'fatturapa',
            'file_name' => $this->fileName($invoice),
            'xml' => $xml,
        ], 'application/vnd.fatturapa+xml');
    }

    /**
     * @throws RequestException
     */
    #[Override]
    public function submit(EInvoicePayload $payload): EInvoiceSubmissionResult
    {
        $xml = $payload->document['xml'] ?? null;

        if (! is_string($xml) || $xml === '') {
            throw new RuntimeException('Aruba e-invoice payload XML is missing.');
        }

        $this->validateXml($xml);

        $endpoint = $this->endpoint($this->submitPath());

        $response = Http::withToken($this->token())
            ->acceptJson()
            ->asJson()
            ->post($endpoint, [
                'file_name' => $this->stringValue($payload->document['file_name'] ?? 'invoice.xml'),
                'mime_type' => $payload->mimeType,
                'xml_base64' => base64_encode($xml),
            ])
            ->throw();

        $body = $response->json();
        $external_id = $this->externalIdFromResponse($body);

        return new EInvoiceSubmissionResult(
            externalId: $external_id,
            success: true,
            message: $this->messageFromResponse($body),
            raw: [
                'provider' => $this->code(),
                'external_id' => $external_id,
                'status' => $this->stringValue($body['status'] ?? null),
                'response' => $body,
            ],
        );
    }

    #[Override]
    public function validateXml(string $xml): void
    {
        $this->builder->validateXml($xml);
    }

    /**
     * @throws RequestException
     */
    #[Override]
    public function remoteStatus(string $externalId): EInvoiceRemoteStatus
    {
        $endpoint = $this->endpoint($this->statusPath($externalId));

        $response = Http::withToken($this->token())
            ->acceptJson()
            ->get($endpoint)
            ->throw();

        $body = $response->json();

        return $this->mapRemoteStatus($this->stringValue($body['status'] ?? null));
    }

    private function baseUrl(): string
    {
        $base_url = config('erp.einvoice.aruba.base_url');

        if (! is_string($base_url) || mb_trim($base_url) === '') {
            throw new RuntimeException('Aruba e-invoice base URL is not configured.');
        }

        return mb_rtrim($base_url, '/');
    }

    private function token(): string
    {
        $token = config('erp.einvoice.aruba.token');

        if (! is_string($token) || mb_trim($token) === '') {
            throw new RuntimeException('Aruba e-invoice token is not configured.');
        }

        return $token;
    }

    private function submitPath(): string
    {
        $path = config('erp.einvoice.aruba.submit_path', '/einvoices');

        return is_string($path) && mb_trim($path) !== '' ? $path : '/einvoices';
    }

    private function statusPath(string $external_id): string
    {
        $path = config('erp.einvoice.aruba.status_path', '/einvoices/{external_id}');
        $template = is_string($path) && mb_trim($path) !== '' ? $path : '/einvoices/{external_id}';

        return str_replace('{external_id}', rawurlencode($external_id), $template);
    }

    private function endpoint(string $path): string
    {
        return $this->baseUrl() . '/' . mb_ltrim($path, '/');
    }

    private function fileName(Invoice $invoice): string
    {
        $country = $this->stringValue($invoice->company?->fiscal_country ?? 'IT');
        $tax_id = $this->stringValue($invoice->company?->tax_id ?? $invoice->company_id);
        $reference = preg_replace('/[^A-Za-z0-9_-]/', '', $invoice->reference ?? (string) $invoice->id) ?: (string) $invoice->id;

        return $country . $tax_id . '_' . $reference . '.xml';
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function externalIdFromResponse(array $body): string
    {
        foreach (['id', 'external_id', 'submission_id', 'uuid'] as $key) {
            $value = $body[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        throw new RuntimeException('Aruba e-invoice response does not include an external id.');
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function messageFromResponse(array $body): ?string
    {
        $message = $body['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : null;
    }

    private function mapRemoteStatus(string $status): EInvoiceRemoteStatus
    {
        return match (mb_strtolower($status)) {
            'received', 'queued', 'pending' => EInvoiceRemoteStatus::Pending,
            'processing', 'in_progress', 'sent' => EInvoiceRemoteStatus::Processing,
            'delivered' => EInvoiceRemoteStatus::Delivered,
            'accepted', 'ok' => EInvoiceRemoteStatus::Accepted,
            'rejected', 'discarded', 'ko', 'error' => EInvoiceRemoteStatus::Rejected,
            default => EInvoiceRemoteStatus::Unknown,
        };
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
