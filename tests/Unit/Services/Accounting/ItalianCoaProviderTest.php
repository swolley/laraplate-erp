<?php

declare(strict_types=1);

use Modules\Business\Casts\AccountKind;
use Modules\Business\Services\Accounting\ItalianCoaProvider;

it('exposes at least 100 CoA rows for a full SME baseline', function (): void {
    $definitions = (new ItalianCoaProvider())->definitions();

    expect(count($definitions))->toBeGreaterThanOrEqual(100);
});

it('uses unique codes and valid parent references', function (): void {
    $definitions = (new ItalianCoaProvider())->definitions();
    $codes = [];
    $roots = 0;

    foreach ($definitions as $row) {
        expect(isset($row['code'], $row['name'], $row['kind']))->toBeTrue()
            ->and($row['kind'])->toBeInstanceOf(AccountKind::class);

        expect(isset($codes[$row['code']]))->toBeFalse('Duplicate code ' . $row['code']);
        $codes[$row['code']] = true;

        if ($row['parent_code'] === null) {
            $roots++;
        }
    }

    expect($roots)->toBeGreaterThanOrEqual(5);

    foreach ($definitions as $row) {
        if ($row['parent_code'] === null) {
            continue;
        }

        expect(array_key_exists($row['parent_code'], $codes))->toBeTrue(
            'Missing parent ' . $row['parent_code'] . ' for ' . $row['code'],
        );
    }
});

it('lists every account kind at least once', function (): void {
    $definitions = (new ItalianCoaProvider())->definitions();
    $seen = [];

    foreach ($definitions as $row) {
        $seen[$row['kind']->value] = true;
    }

    foreach (AccountKind::cases() as $kind) {
        expect($seen)->toHaveKey($kind->value);
    }
});
