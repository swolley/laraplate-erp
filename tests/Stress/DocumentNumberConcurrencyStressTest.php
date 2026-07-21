<?php

declare(strict_types=1);

use Modules\ERP\Tests\Support\DocumentNumberConcurrencyHarness;

require_once dirname(__DIR__).'/Support/DocumentNumberConcurrencyHarness.php';

it('allocates fifty unique document numbers under concurrent load', function (): void {
    if (! filter_var(env('RUN_ERP_STRESS_TESTS', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Set RUN_ERP_STRESS_TESTS=1 to run ERP stress tests.');
    }

    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl is required for ERP concurrency stress tests.');
    }

    $result = DocumentNumberConcurrencyHarness::run(50);

    expect($result['errors'])->toBe([])
        ->and($result['failed_children'])->toBe([])
        ->and($result['numbers'])->toHaveCount(50)
        ->and(array_unique($result['numbers']))->toHaveCount(50)
        ->and($result['numbers'])->toBe(array_map(
            static fn (int $number): string => sprintf('2026-%05d', $number),
            range(1, 50),
        ))
        ->and($result['last_number'])->toBe(50)
        ->and($result['sequence_count'])->toBe(1);
})->group('stress');
