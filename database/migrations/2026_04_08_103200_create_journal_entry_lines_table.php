<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $journal_entry_lines_table = ERPTables::JournalEntryLines->value;
        Schema::create($journal_entry_lines_table, function (Blueprint $table) use ($journal_entry_lines_table): void {
            $table->id();
            $table->foreignId('journal_entry_id')
                ->constrained(ERPTables::JournalEntries->value, 'id', "{$journal_entry_lines_table}_entry_id_FK")
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no')->comment('Stable ordering within the entry');
            $table->foreignId('account_id')
                ->constrained(ERPTables::Accounts->value, 'id', "{$journal_entry_lines_table}_account_id_FK")
                ->restrictOnDelete();

            ERPMigrateUtils::moneyColumns($table);

            $table->string('tax_code', 32)->nullable();
            $table->decimal('tax_rate', 8, 4)->nullable();
            $table->string('tax_label')->nullable();
            $table->text('description')->nullable();

            $table->unique(['journal_entry_id', 'line_no'], "{$journal_entry_lines_table}_entry_line_UN");

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::JournalEntryLines->value);
    }
};
