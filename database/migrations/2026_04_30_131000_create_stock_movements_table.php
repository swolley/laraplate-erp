<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\StockMovementDirection;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('item_id')
                ->constrained('items', 'id', 'stock_movements_item_id_FK')
                ->restrictOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses', 'id', 'stock_movements_warehouse_id_FK')
                ->restrictOnDelete();
            $table->enum('direction', array_map(
                static fn (StockMovementDirection $d): string => $d->value,
                StockMovementDirection::cases(),
            ));
            $table->unsignedInteger('quantity')->comment('Always positive; sign implied by direction');
            $table->decimal('unit_cost', 15, 4)->nullable()->comment('Document or computed unit cost for this movement');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->index(['company_id', 'item_id', 'warehouse_id'], 'stock_movements_company_item_wh_idx');
            $table->index(['source_type', 'source_id'], 'stock_movements_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
