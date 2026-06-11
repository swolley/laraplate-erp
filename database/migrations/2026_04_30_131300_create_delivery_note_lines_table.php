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
        $delivery_note_lines_table = ERPTables::DeliveryNoteLines->value;
        Schema::create($delivery_note_lines_table, function (Blueprint $table) use ($delivery_note_lines_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('delivery_note_id')
                ->constrained(ERPTables::DeliveryNotes->value, 'id', "{$delivery_note_lines_table}_delivery_note_id_FK")
                ->cascadeOnDelete();
            $table->foreignId('item_id')
                ->constrained(ERPTables::Items->value, 'id', "{$delivery_note_lines_table}_item_id_FK")
                ->restrictOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained(ERPTables::Warehouses->value, 'id', "{$delivery_note_lines_table}_warehouse_id_FK")
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->foreignId('sales_order_line_id')
                ->nullable()
                ->constrained(ERPTables::SalesOrderLines->value, 'id', "{$delivery_note_lines_table}_sales_order_line_id_FK")
                ->nullOnDelete();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true, hasLocks: false);

            $table->index(['delivery_note_id'], "{$delivery_note_lines_table}_delivery_note_id_idx");
            $table->index(
                ['company_id', 'item_id', 'warehouse_id'],
                "{$delivery_note_lines_table}_company_item_wh_idx",
            );
        });

        ERPMigrateUtils::positiveCheck($delivery_note_lines_table, 'dnl_qty_pos_ck', 'quantity');
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::DeliveryNoteLines->value);
    }
};
