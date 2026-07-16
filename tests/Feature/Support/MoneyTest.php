<?php

declare(strict_types=1);

use Modules\ERP\ValueObjects\Money;

it('performs exact same-currency money arithmetic', function (): void {
    $money = Money::of('10.1250', 'eur')
        ->add(Money::of('0.8750', 'EUR'))
        ->subtract(Money::of('1.0000', 'EUR'))
        ->multiply('2');

    expect($money->amount)->toBe('20.0000')
        ->and($money->currency)->toBe('EUR')
        ->and((string) $money)->toBe('20.0000 EUR')
        ->and($money->equals(Money::of('20', 'EUR')))->toBeTrue();
});

it('rejects cross-currency money arithmetic', function (): void {
    expect(fn () => Money::of('1', 'EUR')->add(Money::of('1', 'USD')))
        ->toThrow(InvalidArgumentException::class, 'Money currency mismatch.');
});

it('allocates rounding remainder to the first money split', function (): void {
    $parts = Money::of('10.0000', 'EUR')->allocate(3);

    expect($parts)->toHaveCount(3)
        ->and($parts[0]->amount)->toBe('3.3334')
        ->and($parts[1]->amount)->toBe('3.3333')
        ->and($parts[2]->amount)->toBe('3.3333');
});
