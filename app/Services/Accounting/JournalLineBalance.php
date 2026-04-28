<?php

declare(strict_types=1);

namespace Modules\Business\Services\Accounting;

use Brick\Math\BigDecimal;
use Modules\Business\Exceptions\UnbalancedJournalException;

/**
 * Validates that signed functional-currency amounts net to zero (double-entry balance).
 */
final class JournalLineBalance
{
    /**
     * @param  list<int|float|string>  $amount_local_values  Signed decimals; debits positive, credits negative.
     */
    public static function assertBalanced(array $amount_local_values): void
    {
        $sum = BigDecimal::zero();

        foreach ($amount_local_values as $raw) {
            $sum = $sum->plus(BigDecimal::of(self::normalizeToDecimalString($raw)));
        }

        if (! $sum->isEqualTo(BigDecimal::zero())) {
            throw UnbalancedJournalException::forNonZeroSum($sum->toScale(4)->__toString());
        }
    }

    private static function normalizeToDecimalString(string|int|float $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return number_format((float) $value, 4, '.', '');
    }

    /**
     * Decimal string with the opposite sign (for reversal lines).
     */
    public static function negated(string|int|float $value): string
    {
        return BigDecimal::of(self::normalizeToDecimalString($value))
            ->multipliedBy(-1)
            ->toScale(4)
            ->__toString();
    }
}
