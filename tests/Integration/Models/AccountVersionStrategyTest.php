<?php

declare(strict_types=1);

use Modules\ERP\Models\Account;
use Overtrue\LaravelVersionable\VersionStrategy;

it('forces DIFF versioning on Account regardless of settings', function (): void {
    $reflection = new ReflectionClass(Account::class);
    $defaults = $reflection->getDefaultProperties();

    expect($defaults)->toHaveKey('versionStrategy')
        ->and($defaults['versionStrategy'])->toBe(VersionStrategy::DIFF);
});
