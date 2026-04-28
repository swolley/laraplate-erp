<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Helpers\BusinessMigrateUtils;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table): void {
            $table->id();
            BusinessMigrateUtils::companyForeign($table);
            $table->unsignedSmallInteger('year')->comment('Calendar or fiscal label year (e.g. 2026)');
            $table->date('start_date')->comment('First inclusive day of the fiscal year');
            $table->date('end_date')->comment('Last inclusive day of the fiscal year');
            $table->boolean('is_closed')->default(false)->index();

            $table->unique(['company_id', 'year'], 'fiscal_years_company_year_UN');
            $table->index(['company_id', 'start_date'], 'fiscal_years_company_start_idx');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_years');
    }
};
