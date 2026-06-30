<?php

declare(strict_types=1);

namespace Modules\ERP\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Decimal-exact money math (scale 4, HALF_UP) shared across ERP accounting services.
 *
 * All inputs and outputs are decimal strings so values round-trip through the database
 * decimal columns without float drift.
 */
final class Decimal
{
    private const int SCALE = 4;

    public static function add(string $a, string $b): string
    {
        return self::format(BigDecimal::of($a)->plus(BigDecimal::of($b)));
    }

    public static function sub(string $a, string $b): string
    {
        return self::format(BigDecimal::of($a)->minus(BigDecimal::of($b)));
    }

    public static function mul(string $a, string $b): string
    {
        return self::format(BigDecimal::of($a)->multipliedBy(BigDecimal::of($b)));
    }

    /**
     * @throws InvalidArgumentException when the divisor is zero at ERP decimal scale.
     */
    public static function div(string $a, string $b): string
    {
        if (self::isZero($b)) {
            throw new InvalidArgumentException('Decimal division by zero is not allowed.');
        }

        return self::format(BigDecimal::of($a)->dividedBy(BigDecimal::of($b), self::SCALE, RoundingMode::HALF_UP));
    }

    public static function negate(string $a): string
    {
        return self::format(BigDecimal::of($a)->negated());
    }

    public static function abs(string $a): string
    {
        return self::format(BigDecimal::of($a)->abs());
    }

    public static function isZero(string $a): bool
    {
        return self::scaled($a)->isZero();
    }

    public static function isNegative(string $a): bool
    {
        return self::scaled($a)->isNegative();
    }

    public static function format(BigDecimal|string $value): string
    {
        return self::scaled($value)->__toString();
    }

    private static function scaled(BigDecimal|string $value): BigDecimal
    {
        $decimal = $value instanceof BigDecimal ? $value : BigDecimal::of($value);

        return $decimal->toScale(self::SCALE, RoundingMode::HALF_UP);
    }
}
