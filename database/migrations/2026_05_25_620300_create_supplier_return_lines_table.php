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
        $supplier_return_lines_table = ERPTables::SupplierReturnLines->value;

        Schema::create($supplier_return_lines_table, function (Blueprint $table) use ($supplier_return_lines_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('supplier_return_id')->constrained(ERPTables::SupplierReturns->value, 'id', "{$supplier_return_lines_table}_supplier_return_id_FK")->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained(ERPTables::PurchaseOrderLines->value, 'id', "{$supplier_return_lines_table}_purchase_order_line_id_FK")->nullOnDelete();
            $table->foreignId('goods_receipt_line_id')->nullable()->constrained(ERPTables::GoodsReceiptLines->value, 'id', "{$supplier_return_lines_table}_goods_receipt_line_id_FK")->nullOnDelete();
            $table->foreignId('delivery_note_line_id')->nullable()->constrained(ERPTables::DeliveryNoteLines->value, 'id', "{$supplier_return_lines_table}_delivery_note_line_id_FK")->nullOnDelete();
            $table->foreignId('item_id')->constrained(ERPTables::Items->value, 'id', "{$supplier_return_lines_table}_item_id_FK")->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained(ERPTables::Warehouses->value, 'id', "{$supplier_return_lines_table}_warehouse_id_FK")->restrictOnDelete();
            $table->decimal('quantity', 15, 4);

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
        });

        ERPMigrateUtils::positiveCheck($supplier_return_lines_table, 'srl_qty_pos_ck', 'quantity');
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::SupplierReturnLines->value);
    }
};
