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
        $dimensions = ERPTables::AnalyticDimensions->value;
        $values = ERPTables::AnalyticDimensionValues->value;
        $pivot = ERPTables::JournalEntryLineAnalyticDimensionValue->value;

        Schema::create($dimensions, function (Blueprint $table) use ($dimensions): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->string('code', 32);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unique(['company_id', 'code'], "{$dimensions}_company_code_UN");
            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
        });

        Schema::create($values, function (Blueprint $table) use ($dimensions, $values): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('analytic_dimension_id')
                ->constrained($dimensions, 'id', "{$values}_dimension_id_FK")
                ->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unique(['analytic_dimension_id', 'code'], "{$values}_dimension_code_UN");
            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
        });

        Schema::create($pivot, function (Blueprint $table) use ($pivot, $values): void {
            $table->id();
            $table->foreignId('journal_entry_line_id')
                ->constrained(ERPTables::JournalEntryLines->value, 'id', "{$pivot}_line_id_FK")
                ->cascadeOnDelete();
            $table->foreignId('analytic_dimension_value_id')
                ->constrained($values, 'id', "{$pivot}_value_id_FK")
                ->restrictOnDelete();
            $table->decimal('allocation_percent', 7, 4)->default(100);
            $table->unique(['journal_entry_line_id', 'analytic_dimension_value_id'], "{$pivot}_line_value_UN");
            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
        });

        ERPMigrateUtils::positiveCheck($pivot, 'jel_adv_alloc_pos_ck', 'allocation_percent');
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::JournalEntryLineAnalyticDimensionValue->value);
        Schema::dropIfExists(ERPTables::AnalyticDimensionValues->value);
        Schema::dropIfExists(ERPTables::AnalyticDimensions->value);
    }
};
