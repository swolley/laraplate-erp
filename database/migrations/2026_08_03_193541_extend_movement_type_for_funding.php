<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\ERP\Casts\MovementType;
use Modules\ERP\Enums\ERPTables;

/**
 * Extends the enum/check constraint used by each supported database driver.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = app('db')->connection();

        $driver = $connection->getDriverName();
        $types = MovementType::values();

        if ($driver === 'sqlite') {
            $connection->getSchemaBuilder()->table(
                ERPTables::Movements->value,
                static fn (Blueprint $table) => $table->enum('type', $types)->change(),
            );

            return;
        }

        $values = implode(',', array_map(
            static fn (MovementType $type): string => "'{$type->value}'",
            MovementType::cases(),
        ));
        $grammar = $connection->getQueryGrammar();
        $wrapped_table = $grammar->wrapTable(ERPTables::Movements->value);
        $wrapped_column = $grammar->wrap('type');

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $connection->statement(
                "ALTER TABLE {$wrapped_table} MODIFY {$wrapped_column} ENUM({$values}) NOT NULL",
            );

            return;
        }

        if ($driver === 'pgsql') {
            $constraint = $grammar->wrap(
                $connection->getTablePrefix() . ERPTables::Movements->value . '_type_check',
            );
            $connection->statement("ALTER TABLE {$wrapped_table} DROP CONSTRAINT IF EXISTS {$constraint}");
            $connection->statement(
                "ALTER TABLE {$wrapped_table} ADD CONSTRAINT {$constraint} CHECK ({$wrapped_column} IN ({$values}))",
            );
        }
    }

    public function down(): void
    {
        // Shrinking a live enum could invalidate contribution or withdrawal rows.
    }
};
