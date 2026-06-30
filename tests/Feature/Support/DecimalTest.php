<?php

declare(strict_types=1);

use Modules\ERP\Support\Decimal;

it('adds and subtracts at scale 4', function (): void {
    expect(Decimal::add('0.1', '0.2'))->toBe('0.3000')
        ->and(Decimal::add('0.9999', '7.7777'))->toBe('8.7776')
        ->and(Decimal::sub('8.7776', '7.7777'))->toBe('0.9999');
});

it('multiplies and formats at scale 4', function (): void {
    expect(Decimal::mul('3', '0.3333'))->toBe('0.9999')
        ->and(Decimal::mul('7', '1.1111'))->toBe('7.7777')
        ->and(Decimal::format('5'))->toBe('5.0000');
});

it('divides with HALF_UP at the 4th decimal boundary', function (): void {
    // 0.125 / 100 = 0.00125 -> HALF_UP scale 4 -> 0.0013 (the float pipeline rounds this down).
    expect(Decimal::div('0.125', '100'))->toBe('0.0013')
        ->and(Decimal::div('1', '3'))->toBe('0.3333');
});

it('negates, takes absolute value, and reports sign/zero', function (): void {
    expect(Decimal::negate('1.2300'))->toBe('-1.2300')
        ->and(Decimal::abs('-1.2300'))->toBe('1.2300')
        ->and(Decimal::isZero('0.0000'))->toBeTrue()
        ->and(Decimal::isZero('0.0001'))->toBeFalse()
        ->and(Decimal::isNegative('-0.0001'))->toBeTrue()
        ->and(Decimal::isNegative('0.0000'))->toBeFalse();
});
