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
        $table_name = ERPTables::ReportSnapshots->value;

        Schema::create($table_name, function (Blueprint $table) use ($table_name): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->string('report_key', 80);
            $table->string('title');
            $table->json('parameters')->nullable();
            $table->json('snapshot_payload');
            $table->longText('csv_content')->nullable();
            $table->longText('pdf_content')->nullable();
            $table->string('content_hash', 128);
            $table->timestamp('generated_at');
            $table->boolean('is_immutable')->default(true);
            $table->index(['company_id', 'report_key'], "{$table_name}_company_report_idx");
            $table->unique('content_hash', "{$table_name}_hash_UN");

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::ReportSnapshots->value);
    }
};
