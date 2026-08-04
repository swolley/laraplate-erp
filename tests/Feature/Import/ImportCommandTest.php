<?php

declare(strict_types=1);

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Core\Import\Contracts\BulkImporterInterface as CoreBulkImporterInterface;
use Modules\ERP\Console\ImportCommand;
use Modules\ERP\Import\Contracts\BulkImporterInterface;
use Modules\ERP\Import\Support\SiblingImportersDiscovery;
use Modules\ERP\Tests\Stubs\Import\FailingErpImporter;
use Modules\ERP\Tests\Stubs\Import\FakeImportRow;
use Modules\ERP\Tests\Stubs\Import\SuccessfulErpImporter;
use Modules\ERP\Tests\Stubs\Import\WrongModuleImporter;

uses(RefreshDatabase::class);

function erpFakeImportRows(?string $connection_name = null): Builder
{
    $model = (new FakeImportRow)->setConnection($connection_name ?? config('database.default'));

    return $model->getConnection()->table($model->getTable());
}

beforeEach(function (): void {
    erpFakeImportRows()->getConnection()->getSchemaBuilder()->create(FakeImportRow::TABLE, static function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    config([
        'database.connections.erp_import_affinity' => [
            ...config('database.connections.sqlite'),
            'database' => ':memory:',
        ],
    ]);
    DB::purge('erp_import_affinity');
    erpFakeImportRows('erp_import_affinity')->getConnection()->getSchemaBuilder()->create(FakeImportRow::TABLE, static function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    SuccessfulErpImporter::$lastArguments = [];
    $this->app->instance(
        SiblingImportersDiscovery::class,
        new SiblingImportersDiscovery(sys_get_temp_dir() . '/erp-importers-absent-' . uniqid('', true)),
    );
});

afterEach(function (): void {
    erpFakeImportRows()->getConnection()->getSchemaBuilder()->dropIfExists(FakeImportRow::TABLE);
    erpFakeImportRows('erp_import_affinity')->getConnection()->getSchemaBuilder()->dropIfExists(FakeImportRow::TABLE);
    DB::purge('erp_import_affinity');
});

it('registers the ERP import command through module discovery', function (): void {
    expect(Artisan::all())->toHaveKey('erp:import');
});

it('inherits the shared import options under the ERP command identity', function (): void {
    $command = Artisan::all()['erp:import'];
    $definition = $command->getDefinition();

    expect($command->getName())->toBe('erp:import')
        ->and($command->getDescription())->toContain('<fg=green>(💼 Modules\\ERP)</fg=green>')
        ->and($definition->getArguments())->toBe([])
        ->and(array_keys($definition->getOptions()))->toContain(
            'importer',
            'bootstrap',
            'arg',
            'dry-run',
            'limit',
            'no-search',
        )
        ->and(is_subclass_of(BulkImporterInterface::class, CoreBulkImporterInterface::class))->toBeTrue();
});

it('resolves ERP importers and forwards constructor arguments and limits', function (): void {
    $this->artisan(ImportCommand::class, [
        '--importer' => SuccessfulErpImporter::class,
        '--arg' => ['records=5'],
        '--limit' => 2,
    ])
        ->expectsOutputToContain('Imported 2 record(s)')
        ->assertSuccessful();

    expect(erpFakeImportRows()->count())->toBe(2)
        ->and(SuccessfulErpImporter::$lastArguments)->toMatchArray([
            'records' => '5',
            'dryRun' => false,
            'limit' => 2,
        ]);
});

it('rolls back dry-run writes on the importer-selected connection', function (): void {
    $this->artisan(ImportCommand::class, [
        '--importer' => SuccessfulErpImporter::class,
        '--arg' => ['records=3', 'connectionName=erp_import_affinity'],
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Dry-run enabled')
        ->assertSuccessful();

    expect(erpFakeImportRows('erp_import_affinity')->count())->toBe(0)
        ->and(erpFakeImportRows()->count())->toBe(0)
        ->and(SuccessfulErpImporter::$lastArguments['dryRun'])->toBeTrue();
});

it('rejects importers that do not implement the ERP marker', function (): void {
    $this->artisan(ImportCommand::class, ['--importer' => WrongModuleImporter::class])
        ->expectsOutputToContain('must implement')
        ->assertFailed();
});

it('reports missing classes and bootstraps', function (array $arguments): void {
    $this->artisan(ImportCommand::class, $arguments)->assertFailed();
})->with([
    'missing class' => [['--importer' => 'Does\\Not\\Exist']],
    'missing bootstrap' => [[
        '--importer' => SuccessfulErpImporter::class,
        '--bootstrap' => '/tmp/erp-import-bootstrap-missing.php',
    ]],
]);

it('surfaces importer failures', function (): void {
    expect(fn () => $this->artisan(ImportCommand::class, ['--importer' => FailingErpImporter::class])->execute())
        ->toThrow(RuntimeException::class, 'Expected ERP importer failure.');
});

it('discovers only ERP importer implementations from the sibling package', function (): void {
    $root = sys_get_temp_dir() . '/erp-import-discovery-' . uniqid('', true);
    $source = $root . '/src/Demo';
    $vendor = $root . '/vendor';
    mkdir($source, 0777, true);
    mkdir($vendor, 0777, true);
    file_put_contents($source . '/SelectableErpImporter.php', <<<'PHP'
<?php
namespace Demo;
final class SelectableErpImporter implements \Modules\ERP\Import\Contracts\BulkImporterInterface
{
    public function import(?\Symfony\Component\Console\Output\OutputInterface $output = null): int { return 0; }
}
PHP);
    file_put_contents($source . '/CmsOnlyImporter.php', <<<'PHP'
<?php
namespace Demo;
final class CmsOnlyImporter implements \Modules\CMS\Import\Contracts\BulkImporterInterface
{
    public function import(?\Symfony\Component\Console\Output\OutputInterface $output = null): int { return 0; }
}
PHP);
    file_put_contents($vendor . '/autoload.php', <<<'PHP'
<?php
require dirname(__DIR__) . '/src/Demo/SelectableErpImporter.php';
require dirname(__DIR__) . '/src/Demo/CmsOnlyImporter.php';
PHP);

    try {
        require $vendor . '/autoload.php';
        $discovery = new SiblingImportersDiscovery($root);

        expect($discovery->discoverImplementations())->toBe([Demo\SelectableErpImporter::class]);
    } finally {
        erpRemoveDirectory($root);
    }
});

function erpRemoveDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $items = scandir($directory);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . '/' . $item;

        if (is_dir($path)) {
            erpRemoveDirectory($path);
        } else {
            unlink($path);
        }
    }

    rmdir($directory);
}
