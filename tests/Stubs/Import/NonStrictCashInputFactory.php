<?php

namespace Modules\ERP\Tests\Stubs\Import;

use Carbon\CarbonImmutable;
use Modules\ERP\Casts\MovementType;
use Modules\ERP\Import\Data\ExternalCashMovementInput;

final class NonStrictCashInputFactory
{
    public static function withFloatAmount(): ExternalCashMovementInput
    {
        return new ExternalCashMovementInput(
            companyId: 1,
            type: MovementType::Contribution,
            occurredOn: CarbonImmutable::parse('2022-12-03'),
            amount: 5.0,
            currency: 'EUR',
            counterpartyAccountId: 1,
            description: null,
            sourceKey: 'legacy_symfony:nebula',
            externalId: 'payment:float',
            fingerprint: hash('sha256', 'float payment'),
        );
    }
}
