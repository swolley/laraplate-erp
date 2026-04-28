<?php

declare(strict_types=1);

use Modules\Business\Services\Accounting\DocumentNumberAllocator;

it('formats numbers with fiscal year segment when year is positive', function (): void {
    expect(DocumentNumberAllocator::formatDisplay('INV-', 2026, 7, 5))
        ->toBe('INV-2026-00007');
});

it('formats numbers without year segment when fiscal year is zero', function (): void {
    expect(DocumentNumberAllocator::formatDisplay('Q', 0, 12, 4))
        ->toBe('Q0012');
});
