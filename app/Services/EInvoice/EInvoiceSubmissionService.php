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

        $remote_status = $this->provider->remoteStatus($submission->external_id);
        $status = $this->mapRemoteStatus($remote_status, $submission->status);

        $payload = $submission->response_payload ?? [];
        $payload['last_remote_status'] = $remote_status->value;
        $payload['last_refreshed_at'] = now()->toISOString();

        $submission->status = $status;
        $submission->response_payload = $payload;
        $submission->save();

        return $submission;
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
}
