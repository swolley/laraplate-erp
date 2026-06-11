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
        $stock_levels_table = ERPTables::StockLevels->value;
        Schema::create($stock_levels_table, function (Blueprint $table) use ($stock_levels_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('item_id')
                ->constrained(ERPTables::Items->value, 'id', "{$stock_levels_table}_item_id_FK")
                ->cascadeOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained(ERPTables::Warehouses->value, 'id', "{$stock_levels_table}_warehouse_id_FK")
                ->cascadeOnDelete();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('weighted_avg_cost', 15, 4)->default(0);

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
            $table->unique(['company_id', 'item_id', 'warehouse_id'], "{$stock_levels_table}_company_item_wh_un");
        });

        ERPMigrateUtils::nonNegativeCheck($stock_levels_table, 'sl_qty_nn_ck', 'quantity');
        ERPMigrateUtils::nonNegativeCheck($stock_levels_table, 'sl_wac_nn_ck', 'weighted_avg_cost');
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::StockLevels->value);
    }
};
