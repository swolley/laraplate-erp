<?php

declare(strict_types=1);

namespace Modules\ERP\Import\Data;

use Carbon\CarbonImmutable;
use Brick\Math\Exception\NumberFormatException;
use InvalidArgumentException;
use Modules\Core\Import\ValueObjects\ExternalRecordIdentity;
use Modules\ERP\Casts\MovementType;
use Modules\ERP\Support\Decimal;

final readonly class ExternalCashMovementInput
{
    public int $companyId;

    public MovementType $type;

    public CarbonImmutable $occurredOn;

    public string $amount;

    public string $currency;

    public int $counterpartyAccountId;

    public ?string $description;

    public bool $post;

    private ExternalRecordIdentity $externalIdentity;

    public function __construct(
        int $companyId,
        MovementType $type,
        CarbonImmutable $occurredOn,
        string $amount,
        string $currency,
        int $counterpartyAccountId,
        ?string $description,
        string $sourceKey,
        string $externalId,
        string $fingerprint,
        ?CarbonImmutable $sourceUpdatedAt = null,
        bool $post = false,
    ) {
        if ($companyId <= 0 || $counterpartyAccountId <= 0) {
            throw new InvalidArgumentException('Company and counterparty account ids must be positive integers.');
        }

        try {
            $normalized_amount = Decimal::format($amount);
        } catch (NumberFormatException $exception) {
            throw new InvalidArgumentException('Cash movement amount must be a decimal string.', previous: $exception);
        }

        if (Decimal::isZero($normalized_amount) || Decimal::isNegative($normalized_amount)) {
            throw new InvalidArgumentException('Cash movement amount must be greater than zero.');
        }

        $normalized_currency = mb_strtoupper(mb_trim($currency));

        if (preg_match('/\A[A-Z]{3}\z/', $normalized_currency) !== 1) {
            throw new InvalidArgumentException('Cash movement currency must be a three-letter ISO code.');
        }

        $this->companyId = $companyId;
        $this->type = $type;
        $this->occurredOn = $occurredOn;
        $this->amount = $normalized_amount;
        $this->currency = $normalized_currency;
        $this->counterpartyAccountId = $counterpartyAccountId;
        $this->description = $description;
        $this->post = $post;
        $this->externalIdentity = new ExternalRecordIdentity(
            $sourceKey,
            $externalId,
            $fingerprint,
            $sourceUpdatedAt,
        );
    }

    public function identity(): ExternalRecordIdentity
    {
        return $this->externalIdentity;
    }
}
