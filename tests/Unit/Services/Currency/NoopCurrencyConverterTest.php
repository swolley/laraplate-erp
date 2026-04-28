<?php

declare(strict_types=1);

use Modules\Business\Exceptions\UnsupportedCurrencyConversionException;
use Modules\Business\Services\Currency\NoopCurrencyConverter;

it('returns identity rate and amount when source and target currency match', function (): void {
    $converter = new NoopCurrencyConverter();

    $result = $converter->convert('EUR', 'EUR', 1234.56);

    expect($result['rate'])->toBe(1.0)
        ->and($result['amount'])->toBe(1234.56);
});

it('is case- and whitespace-insensitive on currency codes', function (): void {
    $converter = new NoopCurrencyConverter();

    expect($converter->getRate(' eur ', 'EUR'))->toBe(1.0);
});

it('throws UnsupportedCurrencyConversionException for cross-currency conversion', function (): void {
    $converter = new NoopCurrencyConverter();

    expect(fn () => $converter->convert('EUR', 'USD', 100))
        ->toThrow(UnsupportedCurrencyConversionException::class);
});

it('reports both currency codes in the exception message', function (): void {
    try {
        (new NoopCurrencyConverter())->getRate('EUR', 'USD');
        $this->fail('Expected exception was not thrown.');
    } catch (UnsupportedCurrencyConversionException $exception) {
        expect($exception->getMessage())
            ->toContain('EUR')
            ->and($exception->getMessage())->toContain('USD');
    }
});

it('coerces numeric string amounts to float', function (): void {
    $converter = new NoopCurrencyConverter();

    $result = $converter->convert('EUR', 'EUR', '99.99');

    expect($result['amount'])->toBe(99.99);
});
