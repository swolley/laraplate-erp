<?php

declare(strict_types=1);

namespace Modules\ERP\Contracts;

use Modules\ERP\Data\Payments\PaymentCheckout;
use Modules\ERP\Models\PaymentRequest;

interface PaymentRequestProvider
{
    public function code(): string;
    public function createCheckout(PaymentRequest $request): PaymentCheckout;
}
