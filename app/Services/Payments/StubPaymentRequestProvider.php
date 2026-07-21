<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Payments;

use Modules\ERP\Contracts\PaymentRequestProvider;
use Modules\ERP\Data\Payments\PaymentCheckout;
use Modules\ERP\Models\PaymentRequest;

final class StubPaymentRequestProvider implements PaymentRequestProvider
{
    public function code(): string { return 'stub'; }

    public function createCheckout(PaymentRequest $request): PaymentCheckout
    {
        $external_id = 'STUB-PAY-' . $request->getKey();

        return new PaymentCheckout($external_id, "https://payments.invalid/checkout/{$external_id}", [
            'amount' => $request->amount,
            'currency' => $request->currency,
        ]);
    }
}
