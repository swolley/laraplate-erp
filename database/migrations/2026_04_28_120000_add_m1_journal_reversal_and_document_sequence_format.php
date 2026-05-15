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
        $journal_entries_table = ERPTables::JournalEntries->value;
        Schema::table($journal_entries_table, function (Blueprint $table) use ($journal_entries_table): void {
            $table->foreignId('reverses_journal_entry_id')
                ->nullable()
                ->after('description')
                ->constrained($journal_entries_table, 'id', "{$journal_entries_table}_reverses_entry_id_FK")
                ->restrictOnDelete()
                ->comment('Set on reversal vouchers pointing at the original posted entry');
            $table->text('reversal_reason')->nullable()->after('reverses_journal_entry_id');
        });

        Schema::table(ERPTables::DocumentSequences->value, function (Blueprint $table): void {
            $table->string('format_pattern', 255)
                ->nullable()
                ->after('padding')
                ->comment('Optional template; tokens: {prefix},{suffix},{number},{YYYY}; null = built-in fiscal layout');
            $table->string('suffix', 32)
                ->default('')
                ->after('format_pattern')
                ->comment('Trailing segment inserted by {suffix} or default layout');
        });
    }

    public function down(): void
    {
        Schema::table(ERPTables::JournalEntries->value, function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reverses_journal_entry_id');
            $table->dropColumn('reversal_reason');
        });

        Schema::table(ERPTables::DocumentSequences->value, function (Blueprint $table): void {
            $table->dropColumn(['format_pattern', 'suffix']);
        });
    }
};
