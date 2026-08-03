<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\ERP\Casts\MovementType;
use Modules\ERP\Enums\ERPTables;

it('runs return line migration up and down on a prefixed connection', function (): void {
    config()->set('database.connections.erp_affinity', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => 'tenant_',
        'foreign_key_constraints' => true,
    ]);

    DB::purge('erp_affinity');

    $connection = DB::connection('erp_affinity');
    $schema = $connection->getSchemaBuilder();

    $schema->create(ERPTables::InvoiceLines->value, static function (Blueprint $table): void {
        $table->id();
    });
    $schema->create(ERPTables::ReturnOrderLines->value, static function (Blueprint $table): void {
        $table->id();
    });
    $schema->create(ERPTables::SupplierReturnLines->value, static function (Blueprint $table): void {
        $table->id();
    });

    $migration = require module_path('ERP', 'database/migrations/2026_07_11_140257_add_unit_price_to_return_lines_tables.php');

    app('migrator')->usingConnection('erp_affinity', static function () use ($migration, $schema): void {
        $migration->up();

        expect($schema->hasColumn(ERPTables::ReturnOrderLines->value, 'unit_price'))->toBeTrue()
            ->and($schema->hasColumn(ERPTables::SupplierReturnLines->value, 'invoice_line_id'))->toBeTrue()
            ->and($schema->hasColumn(ERPTables::SupplierReturnLines->value, 'unit_price'))->toBeTrue();

        $migration->down();

        expect($schema->hasColumn(ERPTables::ReturnOrderLines->value, 'unit_price'))->toBeFalse()
            ->and($schema->hasColumn(ERPTables::SupplierReturnLines->value, 'invoice_line_id'))->toBeFalse()
            ->and($schema->hasColumn(ERPTables::SupplierReturnLines->value, 'unit_price'))->toBeFalse();
    });
});

it('extends movement types when upgrading an existing sqlite schema', function (): void {
    config()->set('database.connections.erp_movement_upgrade', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    DB::purge('erp_movement_upgrade');

    $connection = DB::connection('erp_movement_upgrade');
    $schema = $connection->getSchemaBuilder();
    $schema->create(ERPTables::Movements->value, static function (Blueprint $table): void {
        $table->id();
        $table->enum('type', [MovementType::Income->value, MovementType::Expense->value]);
    });
    $migration = require module_path('ERP', 'database/migrations/2026_08_03_193541_extend_movement_type_for_funding.php');

    expect(fn () => $connection->table(ERPTables::Movements->value)->insert([
        'type' => MovementType::Contribution->value,
    ]))->toThrow(QueryException::class);

    app('migrator')->usingConnection('erp_movement_upgrade', static function () use ($migration): void {
        $migration->up();
    });

    expect($connection->table(ERPTables::Movements->value)->insert([
        'type' => MovementType::Contribution->value,
    ]))->toBeTrue();
});
