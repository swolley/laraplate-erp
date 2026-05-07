<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\DocumentType;

/**
 * Extends {@see DocumentType} values on `document_sequences` for MySQL installs.
 * SQLite stores enums as unconstrained strings; no change required there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $values = implode(',', array_map(
            static fn (DocumentType $t): string => "'".$t->value."'",
            DocumentType::cases(),
        ));

        DB::statement(
            "ALTER TABLE document_sequences MODIFY document_type ENUM({$values}) NOT NULL COMMENT 'Which document stream this row advances'",
        );
    }

    public function down(): void
    {
        // Reverting enum shrinks is database-specific; left as no-op.
    }
};
