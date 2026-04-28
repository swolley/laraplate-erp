<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years', 'id', 'fiscal_periods_fiscal_year_id_FK')->restrictOnDelete();
            $table->unsignedTinyInteger('period_no')->comment('1-based sequence within the year (typically 1..12 for months)');
            $table->date('start_date')->comment('First inclusive day of the period');
            $table->date('end_date')->comment('Last inclusive day of the period');
            $table->boolean('is_closed')->default(false)->index();

            $table->unique(['fiscal_year_id', 'period_no'], 'fiscal_periods_year_period_UN');
            $table->index(['fiscal_year_id', 'start_date'], 'fiscal_periods_year_start_idx');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: false,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_periods');
    }
};
