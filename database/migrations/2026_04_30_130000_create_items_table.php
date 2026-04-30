<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->string('name');
            $table->string('sku', 64);
            $table->string('uom', 16)->default('unit');
            $table->enum('costing_method', ['fifo', 'weighted_avg'])->default('fifo');
            $table->foreignId('taxonomy_id')
                ->nullable()
                ->constrained('taxonomies', 'id', 'items_taxonomy_id_FK')
                ->nullOnDelete();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
            $table->unique(['company_id', 'sku'], 'items_company_sku_un');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
