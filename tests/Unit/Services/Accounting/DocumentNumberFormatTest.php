<?php

declare(strict_types=1);

use Modules\Business\Models\DocumentSequence;
use Modules\Business\Services\Accounting\DocumentNumberFormatter;
use Tests\TestCase;

uses(TestCase::class);

it('formats numbers with fiscal year segment when year is positive (default layout)', function (): void {
    expect(DocumentNumberFormatter::defaultLayout('INV-', '', 2026, '00007'))
        ->toBe('INV-2026-00007');
});

it('formats numbers without year segment when fiscal year is zero (default layout)', function (): void {
    expect(DocumentNumberFormatter::defaultLayout('Q', '', 0, '0012'))
        ->toBe('Q0012');
});

it('interpolates format_pattern tokens including suffix', function (): void {
    $row = new DocumentSequence;
    $row->forceFill([
        'prefix' => 'IT-',
        'suffix' => '-VAT',
        'padding' => 6,
        'format_pattern' => '{prefix}F-{YYYY}-{number}{suffix}',
    ]);

    expect(DocumentNumberFormatter::format($row, 2026, 3))
        ->toBe('IT-F-2026-000003-VAT');
});

it('uses default layout when format_pattern is null', function (): void {
    $row = new DocumentSequence;
    $row->forceFill([
        'prefix' => 'P',
        'suffix' => 'X',
        'padding' => 4,
        'format_pattern' => null,
    ]);

    expect(DocumentNumberFormatter::format($row, 0, 9))->toBe('P0009X');
});
