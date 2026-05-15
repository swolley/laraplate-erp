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
        $goods_receipt_lines_table = ERPTables::GoodsReceiptLines->value;
        Schema::create($goods_receipt_lines_table, function (Blueprint $table) use ($goods_receipt_lines_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('goods_receipt_id')
                ->constrained(ERPTables::GoodsReceipts->value, 'id', "{$goods_receipt_lines_table}_goods_receipt_id_FK")
                ->cascadeOnDelete();
            $table->foreignId('item_id')
                ->constrained(ERPTables::Items->value, 'id', "{$goods_receipt_lines_table}_item_id_FK")
                ->restrictOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained(ERPTables::Warehouses->value, 'id', "{$goods_receipt_lines_table}_warehouse_id_FK")
                ->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 15, 4);
            $table->foreignId('purchase_order_line_id')
                ->nullable()
                ->constrained(ERPTables::PurchaseOrderLines->value, 'id', "{$goods_receipt_lines_table}_purchase_order_line_id_FK")
                ->nullOnDelete();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true, hasLocks: false);

            $table->index(['goods_receipt_id'], "{$goods_receipt_lines_table}_goods_receipt_id_idx");
            $table->index(
                ['company_id', 'item_id', 'warehouse_id'],
                "{$goods_receipt_lines_table}_company_item_wh_idx",
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::GoodsReceiptLines->value);
    }
};
