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
        $return_order_lines_table = ERPTables::ReturnOrderLines->value;

        Schema::create($return_order_lines_table, function (Blueprint $table) use ($return_order_lines_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('return_order_id')->constrained(ERPTables::ReturnOrders->value, 'id', "{$return_order_lines_table}_return_order_id_FK")->cascadeOnDelete();
            $table->foreignId('invoice_line_id')->nullable()->constrained(ERPTables::InvoiceLines->value, 'id', "{$return_order_lines_table}_invoice_line_id_FK")->nullOnDelete();
            $table->foreignId('delivery_note_line_id')->nullable()->constrained(ERPTables::DeliveryNoteLines->value, 'id', "{$return_order_lines_table}_delivery_note_line_id_FK")->nullOnDelete();
            $table->foreignId('item_id')->constrained(ERPTables::Items->value, 'id', "{$return_order_lines_table}_item_id_FK")->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained(ERPTables::Warehouses->value, 'id', "{$return_order_lines_table}_warehouse_id_FK")->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4)->default(0);

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
        });

        ERPMigrateUtils::positiveCheck($return_order_lines_table, 'rol_qty_pos_ck', 'quantity');
        ERPMigrateUtils::nonNegativeCheck($return_order_lines_table, 'rol_uc_nn_ck', 'unit_cost');
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::ReturnOrderLines->value);
    }
};
