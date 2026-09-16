<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $items_table = ERPTables::Items->value;
        Schema::create($items_table, function (Blueprint $table) use ($items_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->string('name');
            $table->string('sku', 64);
            $table->string('uom', 16)->default('unit');
            $table->enum('costing_method', ['fifo', 'weighted_avg'])->default('fifo');
            $table->foreignId('taxonomy_id')
                ->nullable()
                ->constrained(CoreTables::Taxonomies->value, 'id', "{$items_table}_taxonomy_id_FK")
                ->nullOnDelete();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
            $table->unique(['company_id', 'sku'], "{$items_table}_company_sku_un");
        });

        $price_list_items_table = ERPTables::PriceListItems->value;
        Schema::table($price_list_items_table, function (Blueprint $table) use ($items_table, $price_list_items_table): void {
            $table->foreign('item_id', "{$price_list_items_table}_item_id_FK")
                ->references('id')
                ->on($items_table)
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $price_list_items_table = ERPTables::PriceListItems->value;
        Schema::table($price_list_items_table, function (Blueprint $table) use ($price_list_items_table): void {
            $table->dropForeign("{$price_list_items_table}_item_id_FK");
        });

        Schema::dropIfExists(ERPTables::Items->value);
    }
};
