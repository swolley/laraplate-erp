<?php

declare(strict_types=1);

namespace Modules\Business\Contracts;

use DateTimeInterface;

/**
 * Currency conversion port for the Business / ERP domain.
 *
 * Implementations are responsible for translating amounts between document
 * currency and the company functional (local) currency. They MUST NOT mutate
 * monetary scale — callers are expected to round to the column precision
 * (decimal(15,4)) on persistence.
 *
 * The default container binding is the no-op converter (single-currency mode).
 * Multi-currency providers can be plugged in via the service container.
 */
interface CurrencyConverter
{
    /**
     * Convert `$amount` expressed in `$fromCurrency` into `$toCurrency`.
     *
     * @param  string  $fromCurrency  ISO 4217 source currency code
     * @param  string  $toCurrency    ISO 4217 target currency code
     * @param  float|string|int  $amount        Amount in the source currency
     * @param  DateTimeInterface|null  $at      Reference date for the rate (defaults to "now")
     * @return array{rate: float, amount: float}
     */
    public function convert(
        string $fromCurrency,
        string $toCurrency,
        float|string|int $amount,
        ?DateTimeInterface $at = null,
    ): array;

    /**
     * Resolve the exchange rate from `$fromCurrency` to `$toCurrency`.
     *
     * @param  DateTimeInterface|null  $at  Reference date (defaults to "now")
     */
    public function getRate(
        string $fromCurrency,
        string $toCurrency,
        ?DateTimeInterface $at = null,
    ): float;
}
