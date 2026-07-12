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
        $bank_statement_lines_table = ERPTables::BankStatementLines->value;

        Schema::table($bank_statement_lines_table, function (Blueprint $table) use ($bank_statement_lines_table): void {
            $table->foreignId('difference_journal_entry_id')
                ->nullable()
                ->after('matched_payment_id')
                ->constrained(ERPTables::JournalEntries->value, 'id', "{$bank_statement_lines_table}_difference_journal_entry_id_FK")
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $bank_statement_lines_table = ERPTables::BankStatementLines->value;

        Schema::table($bank_statement_lines_table, function (Blueprint $table) use ($bank_statement_lines_table): void {
            $table->dropForeign("{$bank_statement_lines_table}_difference_journal_entry_id_FK");
            $table->dropColumn('difference_journal_entry_id');
        });
    }
};
