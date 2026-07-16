<?php

declare(strict_types=1);

namespace Modules\ERP\Services\EInvoice;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
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
            'sender_piva' => $this->senderPiva($invoice),
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

        $endpoint = $this->endpoint($this->uploadPath());

        $response = Http::withToken($this->token())
            ->acceptJson()
            ->asJson()
            ->post($endpoint, [
                'dataFile' => base64_encode($xml),
                'credential' => $this->configString('signature_credential'),
                'domain' => $this->configString('signature_domain'),
                'senderPIVA' => $this->stringValue($payload->document['sender_piva'] ?? $this->configString('sender_piva')),
                'skipExtraSchema' => $this->configBool('skip_extra_schema'),
                'dryRun' => $this->configBool('dry_run'),
            ])
            ->throw();

        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $external_id = $this->externalIdFromResponse($body);

        return new EInvoiceSubmissionResult(
            externalId: $external_id,
            success: true,
            message: $this->messageFromResponse($body),
            raw: [
                'provider' => $this->code(),
                'external_id' => $external_id,
                'upload_file_name' => $this->stringValue($body['uploadFileName'] ?? null),
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
        return $this->remoteStatusFromPayload($this->remotePayload($externalId));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function remotePayload(string $externalId): array
    {
        $endpoint = $this->endpoint($this->notificationsPath());

        $response = Http::withToken($this->token())
            ->acceptJson()
            ->get($endpoint, ['filename' => $externalId])
            ->throw();

        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function remoteStatusFromPayload(array $body): EInvoiceRemoteStatus
    {
        $status = $this->stringValue($body['status'] ?? null);

        if ($status !== '') {
            return $this->mapRemoteStatus($status);
        }

        $notification = $this->latestNotification($body);
        $result = mb_strtoupper($this->stringValue($notification['result'] ?? null));
        $doc_type = mb_strtoupper($this->stringValue($notification['docType'] ?? $notification['notifyType'] ?? null));

        if ($result === 'EC01') {
            return EInvoiceRemoteStatus::Accepted;
        }

        if ($result === 'EC02' || $doc_type === 'NS') {
            return EInvoiceRemoteStatus::Rejected;
        }

        return match ($doc_type) {
            'RC', 'MC', 'NE' => EInvoiceRemoteStatus::Delivered,
            'AT' => EInvoiceRemoteStatus::Processing,
            default => EInvoiceRemoteStatus::Unknown,
        };
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

        if (is_string($token) && mb_trim($token) !== '') {
            return $token;
        }

        return $this->signinToken();
    }

    private function signinToken(): string
    {
        $auth_base_url = config('erp.einvoice.aruba.auth_base_url');
        $username = config('erp.einvoice.aruba.username');
        $password = config('erp.einvoice.aruba.password');

        if (! is_string($auth_base_url) || mb_trim($auth_base_url) === '' || ! is_string($username) || mb_trim($username) === '' || ! is_string($password) || mb_trim($password) === '') {
            throw new RuntimeException('Aruba e-invoice token is not configured.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post(mb_rtrim($auth_base_url, '/') . '/auth/signin', [
                'grant_type' => 'password',
                'username' => $username,
                'password' => $password,
            ])
            ->throw();

        $token = $response->json('access_token');

        if (! is_string($token) || mb_trim($token) === '') {
            throw new RuntimeException('Aruba e-invoice signin did not return an access token.');
        }

        return $token;
    }

    private function uploadPath(): string
    {
        $path = config('erp.einvoice.aruba.upload_path', config('erp.einvoice.aruba.submit_path', '/services/invoice/upload'));

        return is_string($path) && mb_trim($path) !== '' ? $path : '/services/invoice/upload';
    }

    private function notificationsPath(): string
    {
        $path = config('erp.einvoice.aruba.notifications_path', config('erp.einvoice.aruba.status_path', '/api/v2/invoices-out/notifications'));

        return is_string($path) && mb_trim($path) !== '' ? $path : '/api/v2/invoices-out/notifications';
    }

    private function endpoint(string $path): string
    {
        return $this->baseUrl() . '/' . mb_ltrim($path, '/');
    }

    private function fileName(Invoice $invoice): string
    {
        $country = $this->companyFiscalCountry($invoice);
        $tax_id = $this->companyTaxId($invoice);
        $reference = preg_replace('/[^A-Za-z0-9_-]/', '', $invoice->reference ?? (string) $invoice->id) ?: (string) $invoice->id;

        return $country . $tax_id . '_' . $reference . '.xml';
    }

    private function senderPiva(Invoice $invoice): string
    {
        $configured = $this->configString('sender_piva');

        if ($configured !== '') {
            return $configured;
        }

        return $this->companyFiscalCountry($invoice) . $this->companyTaxId($invoice);
    }

    private function companyFiscalCountry(Invoice $invoice): string
    {
        return $this->stringValue($invoice->company?->fiscal_country ?? 'IT');
    }

    private function companyTaxId(Invoice $invoice): string
    {
        return $this->stringValue($invoice->company?->tax_id ?? $invoice->company_id);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function externalIdFromResponse(array $body): string
    {
        foreach (['uploadFileName', 'fileName', 'filename', 'id', 'external_id', 'submission_id', 'uuid'] as $key) {
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
        $message = $body['message'] ?? $body['errorDescription'] ?? null;

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

    private function configString(string $key): string
    {
        return $this->stringValue(config('erp.einvoice.aruba.' . $key));
    }

    private function configBool(string $key): bool
    {
        return filter_var(config('erp.einvoice.aruba.' . $key), FILTER_VALIDATE_BOOL);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function latestNotification(array $body): array
    {
        $notifications = $body['notifications'] ?? $body['data'] ?? [];

        if (! is_array($notifications)) {
            return [];
        }

        $latest = Arr::last($notifications);

        return is_array($latest) ? $latest : [];
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
