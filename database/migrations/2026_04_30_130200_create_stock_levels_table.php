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
        Schema::create('stock_levels', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('item_id')
                ->constrained('items', 'id', 'stock_levels_item_id_FK')
                ->cascadeOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses', 'id', 'stock_levels_warehouse_id_FK')
                ->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->decimal('weighted_avg_cost', 15, 4)->default(0);

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
            $table->unique(['company_id', 'item_id', 'warehouse_id'], 'stock_levels_company_item_wh_un');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
