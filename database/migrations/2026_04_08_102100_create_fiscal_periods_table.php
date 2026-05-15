<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;

return new class extends Migration
{
    public function up(): void
    {
        $fiscal_periods_table = ERPTables::FiscalPeriods->value;
        Schema::create($fiscal_periods_table, function (Blueprint $table) use ($fiscal_periods_table): void {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained(ERPTables::FiscalYears->value, 'id', "{$fiscal_periods_table}_fiscal_year_id_FK")->restrictOnDelete();
            $table->unsignedTinyInteger('period_no')->comment('1-based sequence within the year (typically 1..12 for months)');
            $table->date('start_date')->comment('First inclusive day of the period');
            $table->date('end_date')->comment('Last inclusive day of the period');
            $table->boolean('is_closed')->default(false)->index("{$fiscal_periods_table}_is_closed_IDX");

            $table->unique(['fiscal_year_id', 'period_no'], "{$fiscal_periods_table}_year_period_UN");
            $table->index(['fiscal_year_id', 'start_date'], "{$fiscal_periods_table}_year_start_idx");

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: false,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::FiscalPeriods->value);
    }
};
