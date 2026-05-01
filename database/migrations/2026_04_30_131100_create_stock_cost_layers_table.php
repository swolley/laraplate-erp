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
        Schema::create('stock_cost_layers', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('item_id')
                ->constrained('items', 'id', 'stock_cost_layers_item_id_FK')
                ->restrictOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses', 'id', 'stock_cost_layers_warehouse_id_FK')
                ->restrictOnDelete();
            $table->foreignId('stock_movement_id')
                ->constrained('stock_movements', 'id', 'stock_cost_layers_stock_movement_id_FK')
                ->restrictOnDelete();
            $table->unsignedInteger('qty_remaining');
            $table->decimal('unit_cost', 15, 4);

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->index(['company_id', 'item_id', 'warehouse_id'], 'stock_cost_layers_company_item_wh_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_cost_layers');
    }
};
