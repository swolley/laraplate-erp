<?php

declare(strict_types=1);

namespace Modules\ERP\Import\Data;

use Brick\Math\Exception\NumberFormatException;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Core\Import\ValueObjects\ExternalRecordIdentity;
use Modules\ERP\Support\Decimal;

final readonly class ExternalExpenseAllocationInput
{
    /** @var array<int, array{owed: string, paid: string}> */
    public array $shares;

    private ExternalRecordIdentity $externalIdentity;

    /**
     * @param  array<int, array{owed: string, paid: string}>  $shares
     */
    public function __construct(
        public int $movementId,
        public int $partnerPoolId,
        array $shares,
        string $sourceKey,
        string $externalId,
        string $fingerprint,
        ?CarbonImmutable $sourceUpdatedAt = null,
    ) {
        if ($movementId <= 0 || $partnerPoolId <= 0) {
            throw new InvalidArgumentException('Movement and partner pool ids must be positive integers.');
        }

        $normalized_shares = [];

        foreach ($shares as $user_id => $share) {
            if (! is_int($user_id) || $user_id <= 0 || ! is_array($share)
                || ! isset($share['owed'], $share['paid'])
                || ! is_string($share['owed']) || ! is_string($share['paid'])) {
                throw new InvalidArgumentException('Allocation shares require a positive user id and decimal strings for owed and paid.');
            }

            try {
                $normalized_shares[$user_id] = [
                    'owed' => Decimal::format($share['owed']),
                    'paid' => Decimal::format($share['paid']),
                ];
            } catch (NumberFormatException $exception) {
                throw new InvalidArgumentException('Allocation amounts must be decimal strings.', previous: $exception);
            }
        }

        $this->shares = $normalized_shares;
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
