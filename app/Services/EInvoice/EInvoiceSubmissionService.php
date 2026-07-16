<?php

declare(strict_types=1);

namespace Modules\ERP\Services\EInvoice;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\EInvoiceSubmissionStatus;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Contracts\EInvoiceProvider;
use Modules\ERP\Data\EInvoice\EInvoiceRemoteStatus;
use Modules\ERP\Models\EInvoiceSubmission;
use Modules\ERP\Models\Invoice;

final readonly class EInvoiceSubmissionService
{
    public function __construct(
        private EInvoiceProvider $provider,
    ) {}

    public function submit(Invoice $invoice): EInvoiceSubmission
    {
        return DB::transaction(function () use ($invoice): EInvoiceSubmission {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->posted_at === null) {
                throw ValidationException::withMessages([
                    'invoice' => ['Only posted invoices can be submitted as e-invoices.'],
                ]);
            }

            if ($locked->direction !== InvoiceDirection::Sale) {
                throw ValidationException::withMessages([
                    'invoice' => ['Only sale invoices can be submitted as e-invoices.'],
                ]);
            }

            $this->validateFatturaPaReadiness($locked);

            $has_active_submission = $locked->eInvoiceSubmissions()
                ->whereIn('status', [
                    EInvoiceSubmissionStatus::Submitted->value,
                    EInvoiceSubmissionStatus::Accepted->value,
                ])
                ->exists();

            if ($has_active_submission) {
                throw ValidationException::withMessages([
                    'invoice' => ['This invoice already has an active e-invoice submission.'],
                ]);
            }

            $payload = $this->provider->prepare($locked);
            $result = $this->provider->submit($payload);

            return EInvoiceSubmission::query()->create([
                'company_id' => $locked->company_id,
                'invoice_id' => $locked->id,
                'provider_code' => $this->provider->code(),
                'external_id' => $result->externalId,
                'status' => $result->success
                    ? EInvoiceSubmissionStatus::Submitted
                    : EInvoiceSubmissionStatus::Error,
                'submitted_at' => now(),
                'response_payload' => [
                    'message' => $result->message,
                    'raw' => $result->raw,
                ],
            ]);
        });
    }

    public function refresh(EInvoiceSubmission $submission): EInvoiceSubmission
    {
        if ($submission->external_id === null || $submission->external_id === '') {
            throw ValidationException::withMessages([
                'external_id' => ['Cannot refresh an e-invoice submission without an external id.'],
            ]);
        }

        $provider_payload = null;

        if ($this->provider instanceof ArubaEInvoiceProvider) {
            $provider_payload = $this->provider->remotePayload($submission->external_id);
            $remote_status = $this->provider->remoteStatusFromPayload($provider_payload);
        } else {
            $remote_status = $this->provider->remoteStatus($submission->external_id);
        }

        $status = $this->mapRemoteStatus($remote_status, $submission->status);

        $payload = $submission->response_payload ?? [];
        $payload['last_remote_status'] = $remote_status->value;
        $payload['last_refreshed_at'] = now()->toISOString();

        if ($provider_payload !== null) {
            $payload['provider_poll'] = $provider_payload;

            if (array_key_exists('pddAvailable', $provider_payload)) {
                $payload['conservation'] = array_merge($payload['conservation'] ?? [], [
                    'pdd_available' => (bool) $provider_payload['pddAvailable'],
                ]);
            }
        }

        $submission->status = $status;
        $submission->response_payload = $payload;
        $submission->save();

        return $submission;
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    public function applyProviderCallback(string $provider_code, array $callback): EInvoiceSubmission
    {
        $external_id = $this->callbackExternalId($callback);

        if ($external_id === '') {
            throw ValidationException::withMessages([
                'callback' => ['Provider callback does not include an invoice filename.'],
            ]);
        }

        return DB::transaction(function () use ($provider_code, $callback, $external_id): EInvoiceSubmission {
            /** @var EInvoiceSubmission $submission */
            $submission = EInvoiceSubmission::query()
                ->where('provider_code', $provider_code)
                ->where('external_id', $external_id)
                ->lockForUpdate()
                ->latest('id')
                ->firstOrFail();

            $remote_status = $this->remoteStatusFromCallback($callback);
            $payload = $submission->response_payload ?? [];
            $payload['callbacks'] ??= [];
            $payload['callbacks'][] = $callback;
            $payload['last_remote_status'] = $remote_status->value;
            $payload['last_callback_at'] = now()->toISOString();

            if ($provider_code === 'aruba') {
                $payload['aruba'] = array_merge($payload['aruba'] ?? [], [
                    'sdi_identification' => $this->stringValue($callback['sdiIdentification'] ?? null),
                    'last_notify_type' => $this->stringValue($callback['notifyType'] ?? null),
                    'last_notify_file_name' => $this->stringValue($callback['notifyFileName'] ?? null),
                ]);
            }

            $submission->status = $this->mapRemoteStatus($remote_status, $submission->status);
            $submission->response_payload = $payload;
            $submission->save();

            return $submission;
        });
    }

    private function mapRemoteStatus(
        EInvoiceRemoteStatus $remote_status,
        EInvoiceSubmissionStatus $current_status,
    ): EInvoiceSubmissionStatus {
        return match ($remote_status) {
            EInvoiceRemoteStatus::Accepted, EInvoiceRemoteStatus::Delivered => EInvoiceSubmissionStatus::Accepted,
            EInvoiceRemoteStatus::Rejected => EInvoiceSubmissionStatus::Rejected,
            EInvoiceRemoteStatus::Pending, EInvoiceRemoteStatus::Processing => EInvoiceSubmissionStatus::Submitted,
            EInvoiceRemoteStatus::Unknown => $current_status,
        };
    }

    private function validateFatturaPaReadiness(Invoice $invoice): void
    {
        $invoice->loadMissing(['company', 'party']);

        $company = $invoice->company;
        $party = $invoice->party;
        $messages = [];

        if ($company === null) {
            $messages['company'] = ['A company is required for FatturaPA submission.'];
        } else {
            $this->requireFilled($messages, 'company.tax_id', $company->tax_id, 'Company VAT/tax id is required for FatturaPA submission.');
            $this->requireFilled($messages, 'company.fiscal_regime', $company->fiscal_regime, 'Company fiscal regime is required for FatturaPA submission.');
            $this->requireFilled($messages, 'company.legal_address_line', $company->legal_address_line, 'Company legal address is required for FatturaPA submission.');
            $this->requireFilled($messages, 'company.legal_postal_code', $company->legal_postal_code, 'Company legal postal code is required for FatturaPA submission.');
            $this->requireFilled($messages, 'company.legal_city', $company->legal_city, 'Company legal city is required for FatturaPA submission.');
            $this->requireFilled($messages, 'company.legal_country', $company->legal_country, 'Company legal country is required for FatturaPA submission.');
        }

        if ($party === null) {
            $messages['party'] = ['A customer party is required for FatturaPA submission.'];
        } else {
            if (blank($party->vat_number) && blank($party->tax_id)) {
                $messages['party.tax_id'] = ['Customer VAT number or tax id is required for FatturaPA submission.'];
            }

            $this->requireFilled($messages, 'party.fiscal_country', $party->fiscal_country, 'Customer fiscal country is required for FatturaPA submission.');
            $this->requireFilled($messages, 'party.address_line', $party->address_line, 'Customer address is required for FatturaPA submission.');
            $this->requireFilled($messages, 'party.postal_code', $party->postal_code, 'Customer postal code is required for FatturaPA submission.');
            $this->requireFilled($messages, 'party.city', $party->city, 'Customer city is required for FatturaPA submission.');
            $this->requireFilled($messages, 'party.country', $party->country, 'Customer country is required for FatturaPA submission.');
        }

        $this->requireFilled($messages, 'invoice.einvoice_transmission_format', $invoice->einvoice_transmission_format, 'FatturaPA transmission format is required.');

        $recipient_code = $invoice->einvoice_recipient_code ?: $party?->einvoice_recipient_code;
        $pec_email = $invoice->einvoice_pec_email ?: $party?->einvoice_pec_email;

        if (blank($recipient_code) && blank($pec_email)) {
            $messages['invoice.einvoice_recipient_code'] = ['Recipient code or PEC email is required for FatturaPA submission.'];
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    /**
     * @param  array<string, array<int, string>>  $messages
     */
    private function requireFilled(array &$messages, string $key, mixed $value, string $message): void
    {
        if (blank($value)) {
            $messages[$key] = [$message];
        }
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    private function callbackExternalId(array $callback): string
    {
        foreach (['invoiceFileName', 'sdiInvoiceFileName', 'uploadFileName', 'filename', 'fileName'] as $key) {
            $value = $callback[$key] ?? null;

            if (is_string($value) && mb_trim($value) !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    private function remoteStatusFromCallback(array $callback): EInvoiceRemoteStatus
    {
        $result = mb_strtoupper($this->stringValue($callback['result'] ?? null));
        $notify_type = mb_strtoupper($this->stringValue($callback['notifyType'] ?? $callback['docType'] ?? null));
        $status = mb_strtolower($this->stringValue($callback['status'] ?? null));

        if ($result === 'EC01') {
            return EInvoiceRemoteStatus::Accepted;
        }

        if ($result === 'EC02' || $notify_type === 'NS') {
            return EInvoiceRemoteStatus::Rejected;
        }

        if (str_contains($status, 'errore') || str_contains($status, 'error')) {
            return EInvoiceRemoteStatus::Rejected;
        }

        return match ($notify_type) {
            'RC', 'MC', 'NE' => EInvoiceRemoteStatus::Delivered,
            'AT' => EInvoiceRemoteStatus::Processing,
            default => EInvoiceRemoteStatus::Unknown,
        };
    }

    private function stringValue(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }
}
