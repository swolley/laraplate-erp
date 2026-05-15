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
        $journal_entries_table = ERPTables::JournalEntries->value;
        Schema::create($journal_entries_table, function (Blueprint $table) use ($journal_entries_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('fiscal_period_id')
                ->nullable()
                ->constrained(ERPTables::FiscalPeriods->value, 'id', "{$journal_entries_table}_fiscal_period_id_FK")
                ->restrictOnDelete();
            $table->timestamp('posted_at')->nullable()->comment('Null until posted; immutable after set');
            $table->unsignedBigInteger('posted_by')->nullable()->index()->comment('users.id when posted; no FK for module isolation');
            $table->nullableMorphs('reference', "{$journal_entries_table}_reference_idx");
            $table->text('description')->nullable();

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::JournalEntries->value);
    }
};
