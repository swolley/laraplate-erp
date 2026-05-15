<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Enums\ERPTables;

return new class extends Migration
{
    public function up(): void
    {
        $journal_entry_lines_table = ERPTables::JournalEntryLines->value;
        Schema::table($journal_entry_lines_table, function (Blueprint $table) use ($journal_entry_lines_table): void {
            $table->foreignId('tax_code_id')
                ->nullable()
                ->after('account_id')
                ->constrained(ERPTables::TaxCodes->value, 'id', "{$journal_entry_lines_table}_tax_code_id_FK")
                ->nullOnDelete()
                ->comment('Optional FK; fiscal strings remain the immutable posting snapshot');
        });
    }

    public function down(): void
    {
        $journal_entry_lines_table = ERPTables::JournalEntryLines->value;
        Schema::table($journal_entry_lines_table, function (Blueprint $table) use ($journal_entry_lines_table): void {
            $table->dropForeign("{$journal_entry_lines_table}_tax_code_id_FK");
            $table->dropColumn('tax_code_id');
        });
    }
};
