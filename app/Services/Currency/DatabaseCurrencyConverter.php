<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Currency;

use DateTimeInterface;
use Modules\ERP\Contracts\CurrencyConverter;
use Modules\ERP\Exceptions\UnsupportedCurrencyConversionException;
use Modules\ERP\Models\ExchangeRate;
use Override;

final class DatabaseCurrencyConverter implements CurrencyConverter
{
    #[Override]
    public function convert(string $fromCurrency, string $toCurrency, float|string|int $amount, ?DateTimeInterface $at = null): array
    {
        $rate = $this->getRate($fromCurrency, $toCurrency, $at);

        return [
            'rate' => $rate,
            'amount' => round((float) $amount * $rate, 4),
        ];
    }

    #[Override]
    public function getRate(string $fromCurrency, string $toCurrency, ?DateTimeInterface $at = null): float
    {
        $from = strtoupper(trim($fromCurrency));
        $to = strtoupper(trim($toCurrency));

        if ($from === $to) {
            return 1.0;
        }

        $date = $at?->format('Y-m-d') ?? now()->toDateString();
        $direct = $this->latestRate($from, $to, $date);

        if ($direct !== null) {
            return $direct;
        }

        $inverse = $this->latestRate($to, $from, $date);

        if ($inverse !== null && $inverse > 0.0) {
            return round(1 / $inverse, 8);
        }

        throw UnsupportedCurrencyConversionException::between($from, $to);
    }

    private function latestRate(string $from, string $to, string $date): ?float
    {
        $rate = ExchangeRate::query()
            ->where('from_currency', $from)
            ->where('to_currency', $to)
            ->whereDate('rate_date', '<=', $date)
            ->latest('rate_date')
            ->latest('id')
            ->value('rate');

        return is_numeric($rate) ? (float) $rate : null;
    }
}
