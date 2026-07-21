<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\User;
use Modules\ERP\Casts\PaymentRequestStatus;
use Modules\ERP\Contracts\PaymentRequestProvider;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PaymentRequest;
use Modules\ERP\Services\Payments\PaymentRequestService;
use Modules\ERP\Services\Payments\StubPaymentRequestProvider;

uses(RefreshDatabase::class);

function paymentRequestFixture(): array
{
    $company = Company::query()->create(['slug' => 'pay-request-'.uniqid(), 'name' => 'Pay Request', 'fiscal_country' => 'IT', 'default_currency' => 'EUR']);
    $party = Party::query()->create(['company_id' => $company->id, 'name' => 'Customer', 'is_customer' => true]);
    $request = PaymentRequest::query()->create([
        'company_id' => $company->id, 'party_id' => $party->id, 'amount' => '42.5000', 'currency' => 'EUR',
        'status' => PaymentRequestStatus::Draft, 'provider_code' => 'stub',
    ]);
    return [$company, $party, $request];
}

it('binds the stub provider and creates a deterministic checkout', function (): void {
    [, , $request] = paymentRequestFixture();
    expect(app(PaymentRequestProvider::class))->toBeInstanceOf(StubPaymentRequestProvider::class);
    $sent = app(PaymentRequestService::class)->send($request);
    expect($sent->status)->toBe(PaymentRequestStatus::Pending)
        ->and($sent->external_id)->toBe('STUB-PAY-'.$request->id)
        ->and($sent->checkout_url)->toBe('https://payments.invalid/checkout/STUB-PAY-'.$request->id)
        ->and($sent->sent_at)->not->toBeNull();
});

it('requires exactly one recipient', function (): void {
    [$company, $party] = paymentRequestFixture();
    $user = User::factory()->create();
    expect(fn () => PaymentRequest::query()->create([
        'company_id' => $company->id, 'party_id' => $party->id, 'user_id' => $user->id,
        'amount' => '10.0000', 'currency' => 'EUR', 'status' => PaymentRequestStatus::Draft, 'provider_code' => 'stub',
    ]))->toThrow(ValidationException::class);
});

it('accepts authenticated provider callbacks and rejects missing credentials', function (): void {
    [, , $request] = paymentRequestFixture();
    $sent = app(PaymentRequestService::class)->send($request);
    config()->set('erp.payment_requests.providers.stub.callback_api_key', 'callback-secret');
    $payload = ['external_id' => $sent->external_id, 'status' => 'paid'];

    $this->postJson('/api/v1/erp/payment-requests/stub/callbacks', $payload)->assertUnauthorized();
    $this->withToken('callback-secret')->postJson('/api/v1/erp/payment-requests/stub/callbacks', $payload)
        ->assertOk()->assertJsonPath('data.status', 'paid');
    expect($sent->fresh()->status)->toBe(PaymentRequestStatus::Paid)
        ->and($sent->fresh()->paid_at)->not->toBeNull();

    $this->withToken('callback-secret')->postJson('/api/v1/erp/payment-requests/stub/callbacks', [
        'external_id' => $sent->external_id,
        'status' => 'failed',
    ])->assertOk()->assertJsonPath('data.status', 'paid');
});
