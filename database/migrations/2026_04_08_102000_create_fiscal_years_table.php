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
        $fiscal_years_table = ERPTables::FiscalYears->value;
        Schema::create($fiscal_years_table, function (Blueprint $table) use ($fiscal_years_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->unsignedSmallInteger('year')->comment('Calendar or fiscal label year (e.g. 2026)');
            $table->date('start_date')->comment('First inclusive day of the fiscal year');
            $table->date('end_date')->comment('Last inclusive day of the fiscal year');
            $table->boolean('is_closed')->default(false)->index("{$fiscal_years_table}_is_closed_IDX");

            $table->unique(['company_id', 'year'], "{$fiscal_years_table}_company_year_UN");
            $table->index(['company_id', 'start_date'], "{$fiscal_years_table}_company_start_idx");

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::FiscalYears->value);
    }
};
