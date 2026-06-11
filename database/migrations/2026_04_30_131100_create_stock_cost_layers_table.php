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
        $stock_cost_layers_table = ERPTables::StockCostLayers->value;
        Schema::create($stock_cost_layers_table, function (Blueprint $table) use ($stock_cost_layers_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('item_id')
                ->constrained(ERPTables::Items->value, 'id', "{$stock_cost_layers_table}_item_id_FK")
                ->restrictOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained(ERPTables::Warehouses->value, 'id', "{$stock_cost_layers_table}_warehouse_id_FK")
                ->restrictOnDelete();
            $table->foreignId('stock_movement_id')
                ->constrained(ERPTables::StockMovements->value, 'id', "{$stock_cost_layers_table}_stock_movement_id_FK")
                ->restrictOnDelete();
            $table->decimal('qty_remaining', 15, 4);
            $table->decimal('unit_cost', 15, 4);

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->index(['company_id', 'item_id', 'warehouse_id'], "{$stock_cost_layers_table}_company_item_wh_idx");
        });

        ERPMigrateUtils::nonNegativeCheck($stock_cost_layers_table, 'scl_qr_nn_ck', 'qty_remaining');
        ERPMigrateUtils::nonNegativeCheck($stock_cost_layers_table, 'scl_uc_nn_ck', 'unit_cost');
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::StockCostLayers->value);
    }
};
