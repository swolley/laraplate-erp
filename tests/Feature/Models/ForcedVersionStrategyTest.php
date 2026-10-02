<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\Concerns\HasVersions;
use Modules\Core\Tests\Support\ForcedModelConfiguration;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\FiscalPeriod;
use Overtrue\LaravelVersionable\VersionStrategy;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Artisan::call('db:seed', ['--class' => CoreDatabaseSeeder::class, '--no-interaction' => true]);
});

it('forces the DIFF version strategy on ERP models, which Core discovers as a forced configuration', function (): void {
    $erp_diff_models = collect(ForcedModelConfiguration::cases())
        ->filter(fn (array $case): bool => $case['property'] === 'versionStrategy'
            && $case['expected'] === VersionStrategy::DIFF
            && str_starts_with($case['model'], 'Modules\\ERP\\'));

    expect($erp_diff_models)->not->toBeEmpty();
});

it('applies the forced DIFF version strategy through HasVersions at runtime', function (): void {
    HasVersions::resetVersionStrategyCache();

    foreach ([FiscalPeriod::class, Account::class] as $model_class) {
        $instance = (new ReflectionClass($model_class))->newInstanceWithoutConstructor();

        expect($instance->getVersionStrategy())->toBe(VersionStrategy::DIFF);
    }
});
