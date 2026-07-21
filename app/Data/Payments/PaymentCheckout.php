<?php

declare(strict_types=1);

namespace Modules\ERP\Data\Payments;

final readonly class PaymentCheckout
{
    public function __construct(public string $externalId, public string $checkoutUrl, public array $payload = []) {}
}
