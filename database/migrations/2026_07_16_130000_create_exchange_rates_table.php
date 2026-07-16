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
        $table_name = ERPTables::ExchangeRates->value;

        Schema::create($table_name, function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->char('from_currency', 3);
            $table->char('to_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->date('rate_date');
            $table->string('source', 80)->nullable();
            $table->unique(['from_currency', 'to_currency', 'rate_date', 'source'], "{$table_name}_pair_date_source_UN");
            $table->index(['from_currency', 'to_currency', 'rate_date'], "{$table_name}_pair_date_idx");

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::ExchangeRates->value);
    }
};
