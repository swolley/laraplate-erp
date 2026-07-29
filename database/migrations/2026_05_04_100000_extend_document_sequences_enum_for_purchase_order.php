<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Enums\ERPTables;

/**
 * Extends {@see DocumentType} values on `document_sequences` for MySQL installs.
 * SQLite stores enums as unconstrained strings; no change required there.
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
            static fn (DocumentType $t): string => "'" . $t->value . "'",
            DocumentType::cases(),
        ));

        $document_sequences_table = ERPTables::DocumentSequences->value;
        $grammar = $connection->getQueryGrammar();
        $wrapped_table = $grammar->wrapTable($document_sequences_table);
        $wrapped_column = $grammar->wrap('document_type');
        $connection->statement(
            "ALTER TABLE {$wrapped_table} MODIFY {$wrapped_column} ENUM({$values}) NOT NULL COMMENT 'Which document stream this row advances'",
        );
    }

    public function down(): void
    {
        // Reverting enum shrinks is database-specific; left as no-op.
    }
};
