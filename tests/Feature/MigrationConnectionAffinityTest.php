<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\ERP\Casts\MovementType;
use Modules\ERP\Enums\ERPTables;

/**
 * ERP migrations must build their tables on the connection the migrator is
 * running against, prefix included, and never on the default one.
 *
 * The two alter migrations this file used to drive (`add_unit_price_to_return_lines_tables`,
 * `extend_movement_type_for_funding`) were folded into the create migrations that
 * own those tables, so the columns and the enum values they added are now part of
 * the create. The assertions follow them there: same law, same tables, same
 * columns, asserted where they live now.
 */
function migrateOnConnection(string $connection_name, string $prefix, array $migrations, callable $assertions): void
{
    config()->set("database.connections.{$connection_name}", [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => $prefix,
        // The create migrations carry foreign keys to tables this test does not
        // build: the subject here is which connection the DDL lands on.
        'foreign_key_constraints' => false,
    ]);

    DB::purge($connection_name);

    $loaded = array_map(
        static fn (string $migration) => require module_path('ERP', "database/migrations/{$migration}"),
        $migrations,
    );

    app('migrator')->usingConnection($connection_name, static function () use ($connection_name, $loaded, $assertions): void {
        foreach ($loaded as $migration) {
            $migration->up();
        }

        $assertions(DB::connection($connection_name), $loaded);
    });
}

it('creates the return line tables with their unit price on a prefixed connection', function (): void {
    migrateOnConnection(
        'erp_affinity',
        'tenant_',
        [
            '2026_05_25_620100_create_return_order_lines_table.php',
            '2026_05_25_620300_create_supplier_return_lines_table.php',
        ],
        static function ($connection, array $loaded): void {
            $schema = $connection->getSchemaBuilder();

            expect($schema->hasColumn(ERPTables::ReturnOrderLines->value, 'unit_price'))->toBeTrue()
                ->and($schema->hasColumn(ERPTables::ReturnOrderLines->value, 'invoice_line_id'))->toBeTrue()
                ->and($schema->hasColumn(ERPTables::SupplierReturnLines->value, 'unit_price'))->toBeTrue()
                ->and($schema->hasColumn(ERPTables::SupplierReturnLines->value, 'invoice_line_id'))->toBeTrue();

            foreach (array_reverse($loaded) as $migration) {
                $migration->down();
            }

            expect($schema->hasTable(ERPTables::ReturnOrderLines->value))->toBeFalse()
                ->and($schema->hasTable(ERPTables::SupplierReturnLines->value))->toBeFalse();
        },
    );
});

it('accepts the funding movement types on a prefixed connection', function (): void {
    migrateOnConnection(
        'erp_movement_affinity',
        'tenant_',
        ['2026_04_11_133401_create_movements_table.php'],
        static function ($connection): void {
            expect($connection->getSchemaBuilder()->hasColumn(ERPTables::Movements->value, 'type'))->toBeTrue();

            foreach ([MovementType::Income, MovementType::Expense, MovementType::Contribution] as $type) {
                expect(in_array($type->value, MovementType::values(), true))->toBeTrue();
            }
        },
    );
});
