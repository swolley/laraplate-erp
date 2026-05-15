<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\StockMovementDirection;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $stock_movements_table = ERPTables::StockMovements->value;
        Schema::create($stock_movements_table, function (Blueprint $table) use ($stock_movements_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('item_id')
                ->constrained(ERPTables::Items->value, 'id', "{$stock_movements_table}_item_id_FK")
                ->restrictOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained(ERPTables::Warehouses->value, 'id', "{$stock_movements_table}_warehouse_id_FK")
                ->restrictOnDelete();
            $table->enum('direction', StockMovementDirection::values())->index("{$stock_movements_table}_direction_IDX");
            $table->unsignedInteger('quantity')->comment('Always positive; sign implied by direction');
            $table->decimal('unit_cost', 15, 4)->nullable()->comment('Document or computed unit cost for this movement');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->index(['company_id', 'item_id', 'warehouse_id'], "{$stock_movements_table}_company_item_wh_idx");
            $table->index(['source_type', 'source_id'], "{$stock_movements_table}_source_idx");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::StockMovements->value);
    }
};
