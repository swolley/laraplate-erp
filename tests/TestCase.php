<?php

declare(strict_types=1);

namespace Modules\ERP\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [];
    }

    protected function defineEnvironment($app): void
    {
        $app->make(\Illuminate\Contracts\Config\Repository::class)->set('erp', require __DIR__ . '/../config/config.php');
        $app->make(\Illuminate\Contracts\Config\Repository::class)->set('database.default', 'testing');
        $app->make(\Illuminate\Contracts\Config\Repository::class)->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {

    }
}
