<?php

declare(strict_types=1);

namespace Modules\Business\Exceptions;

use RuntimeException;

/**
 * Raised when a cross-currency conversion is requested but no real
 * {@see \Modules\Business\Contracts\CurrencyConverter} provider is bound.
 *
 * The default no-op converter only supports identity (same currency) conversions.
 */
final class UnsupportedCurrencyConversionException extends RuntimeException
{
    public static function between(string $from, string $to): self
    {
        return new self(sprintf(
            'Cross-currency conversion from "%s" to "%s" is not supported. '
            . 'Bind a real implementation of %s in the service container before requesting non-identity conversions.',
            $from,
            $to,
            \Modules\Business\Contracts\CurrencyConverter::class,
        ));
    }
}
