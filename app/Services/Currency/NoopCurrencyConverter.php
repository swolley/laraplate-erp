<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Currency;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Modules\ERP\Contracts\CurrencyConverter;
use Modules\ERP\Exceptions\UnsupportedCurrencyConversionException;
use Override;

/**
 * Single-currency, identity-only converter.
 *
 * - Same currency on both sides => rate=1, amount unchanged.
 * - Cross-currency conversion => throws {@see UnsupportedCurrencyConversionException}.
 *
 * Plug a real provider (ECB, fixer.io, etc.) via the service container when
 * multi-currency operation is enabled.
 */
final class NoopCurrencyConverter implements CurrencyConverter
{
    #[Override]
    public function convert(
        Model $owner,
        string $fromCurrency,
        string $toCurrency,
        float|string|int $amount,
        ?DateTimeInterface $at = null,
    ): array {
        $rate = $this->getRate($owner, $fromCurrency, $toCurrency, $at);

        return [
            'rate' => $rate,
            'amount' => (float) $amount * $rate,
        ];
    }

    #[Override]
    public function getRate(
        Model $owner,
        string $fromCurrency,
        string $toCurrency,
        ?DateTimeInterface $at = null,
    ): float {
        $from = mb_strtoupper(mb_trim($fromCurrency));
        $to = mb_strtoupper(mb_trim($toCurrency));

        if ($from === $to) {
            return 1.0;
        }

        throw UnsupportedCurrencyConversionException::between($from, $to);
    }
}
