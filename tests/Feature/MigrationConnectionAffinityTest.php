<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
