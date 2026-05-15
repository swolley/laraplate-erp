<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $time_entries_table = ERPTables::TimeEntries->value;
        Schema::create($time_entries_table, function (Blueprint $table) use ($time_entries_table): void {
            $table->id();
            $table->foreignId('user_id')->constrained(CoreTables::Users->value, 'id', "{$time_entries_table}_user_id_FK")->restrictOnDelete();
            $table->foreignId('taxonomy_id')->constrained(CoreTables::Taxonomies->value, 'id', "{$time_entries_table}_taxonomy_id_FK")->restrictOnDelete();
            $table->foreignId('task_id')->nullable()->constrained(ERPTables::Tasks->value, 'id', "{$time_entries_table}_task_id_FK")->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained(ERPTables::Projects->value, 'id', "{$time_entries_table}_project_id_FK")->nullOnDelete();
            $table->foreignId('quotation_item_id')->nullable()->constrained(ERPTables::QuotationItems->value, 'id', "{$time_entries_table}_quotation_item_id_FK")->nullOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable()->comment('Null while the session is still open');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ERPTables::TimeEntries->value);
    }
};
