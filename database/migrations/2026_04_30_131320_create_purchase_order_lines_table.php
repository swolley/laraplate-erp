<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;

return new class extends Migration
{
    public function up(): void
    {
        $purchase_order_lines_table = ERPTables::PurchaseOrderLines->value;
        Schema::create($purchase_order_lines_table, function (Blueprint $table) use ($purchase_order_lines_table): void {
            $table->id();
            $table->foreignId('purchase_order_id')
                ->constrained(ERPTables::PurchaseOrders->value, 'id', "{$purchase_order_lines_table}_purchase_order_id_FK")
                ->cascadeOnDelete();
            $table->foreignId('item_id')
                ->nullable()
                ->constrained(ERPTables::Items->value, 'id', "{$purchase_order_lines_table}_item_id_FK")
                ->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('qty_ordered')->default(1);
            $table->unsignedInteger('qty_received')->default(0);
            $table->decimal('unit_price', 15, 4)->nullable();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true, hasLocks: false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::PurchaseOrderLines->value);
    }
};
