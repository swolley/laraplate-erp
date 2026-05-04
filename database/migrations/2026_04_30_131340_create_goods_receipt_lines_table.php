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
        Schema::create('goods_receipt_lines', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('goods_receipt_id')
                ->constrained('goods_receipts', 'id', 'goods_receipt_lines_goods_receipt_id_FK')
                ->cascadeOnDelete();
            $table->foreignId('item_id')
                ->constrained('items', 'id', 'goods_receipt_lines_item_id_FK')
                ->restrictOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses', 'id', 'goods_receipt_lines_warehouse_id_FK')
                ->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 15, 4);
            $table->foreignId('purchase_order_line_id')
                ->nullable()
                ->constrained('purchase_order_lines', 'id', 'goods_receipt_lines_purchase_order_line_id_FK')
                ->nullOnDelete();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true, hasLocks: false);

            $table->index(['goods_receipt_id'], 'goods_receipt_lines_goods_receipt_id_idx');
            $table->index(
                ['company_id', 'item_id', 'warehouse_id'],
                'goods_receipt_lines_company_item_wh_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
    }
};
