<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\ERP\Casts\MovementType;
use Modules\ERP\Enums\ERPTables;

/**
 * Extends cash movement types for MySQL installs. SQLite stores enums as
 * unconstrained strings, so no schema change is required there.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = app('db')->connection();

        if ($connection->getDriverName() !== 'mysql') {
            return;
        }

        $values = implode(',', array_map(
            static fn (MovementType $type): string => "'{$type->value}'",
            MovementType::cases(),
        ));
        $grammar = $connection->getQueryGrammar();
        $wrapped_table = $grammar->wrapTable(ERPTables::Movements->value);
        $wrapped_column = $grammar->wrap('type');

        $connection->statement(
            "ALTER TABLE {$wrapped_table} MODIFY {$wrapped_column} ENUM({$values}) NOT NULL",
        );
    }

    public function down(): void
    {
        // Shrinking a live enum could invalidate contribution or withdrawal rows.
    }
};
