<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Payments;

use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\PaymentRequestStatus;
use Modules\ERP\Contracts\PaymentRequestProvider;
use Modules\ERP\Models\PaymentRequest;
use Modules\ERP\Support\ConnectionScopedTransaction;

final readonly class PaymentRequestService
{
    public function __construct(private PaymentRequestProvider $provider) {}

    public function send(PaymentRequest $request): PaymentRequest
    {
        return ConnectionScopedTransaction::run($request, function () use ($request): PaymentRequest {
            $request = PaymentRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if ($request->status !== PaymentRequestStatus::Draft) {
                throw ValidationException::withMessages(['status' => ['Only draft payment requests can be sent.']]);
            }
            if ($request->provider_code !== $this->provider->code()) {
                throw ValidationException::withMessages(['provider_code' => ['The configured provider does not match this request.']]);
            }
            $checkout = $this->provider->createCheckout($request);
            $request->update([
                'external_id' => $checkout->externalId,
                'checkout_url' => $checkout->checkoutUrl,
                'provider_payload' => $checkout->payload,
                'status' => PaymentRequestStatus::Pending,
                'sent_at' => now(),
            ]);

            return $request->refresh();
        });
    }

    public function applyCallback(string $provider, array $payload): PaymentRequest
    {
        $external_id = $payload['external_id'] ?? null;
        if (! is_string($external_id) || $external_id === '') {
            throw ValidationException::withMessages(['external_id' => ['A provider external ID is required.']]);
        }

        $request = PaymentRequest::query()->where('provider_code', $provider)
            ->where('external_id', $external_id)->firstOrFail();

        return ConnectionScopedTransaction::run($request, function () use ($request, $payload): PaymentRequest {
            $request = $request->newQuery()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            if (in_array($request->status, [PaymentRequestStatus::Paid, PaymentRequestStatus::Cancelled], true)) {
                return $request;
            }
            $status = match ($payload['status'] ?? null) {
                'pending' => PaymentRequestStatus::Pending,
                'paid' => PaymentRequestStatus::Paid,
                'failed' => PaymentRequestStatus::Failed,
                'cancelled' => PaymentRequestStatus::Cancelled,
                default => throw ValidationException::withMessages(['status' => ['Unsupported provider payment status.']]),
            };
            $request->update([
                'status' => $status,
                'paid_at' => $status === PaymentRequestStatus::Paid ? now() : $request->paid_at,
                'cancelled_at' => $status === PaymentRequestStatus::Cancelled ? now() : $request->cancelled_at,
                'provider_payload' => array_merge($request->provider_payload ?? [], ['last_callback' => $payload]),
            ]);

            return $request->refresh();
        });
    }
}
