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
        Schema::create('delivery_note_lines', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('delivery_note_id')
                ->constrained('delivery_notes', 'id', 'delivery_note_lines_delivery_note_id_FK')
                ->cascadeOnDelete();
            $table->foreignId('item_id')
                ->constrained('items', 'id', 'delivery_note_lines_item_id_FK')
                ->restrictOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses', 'id', 'delivery_note_lines_warehouse_id_FK')
                ->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->foreignId('sales_order_line_id')
                ->nullable()
                ->constrained('sales_order_lines', 'id', 'delivery_note_lines_sales_order_line_id_FK')
                ->nullOnDelete();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true, hasLocks: false);
            $table->index(['delivery_note_id'], 'delivery_note_lines_delivery_note_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_lines');
    }
};
