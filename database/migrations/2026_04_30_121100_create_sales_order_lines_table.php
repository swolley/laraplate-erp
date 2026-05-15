<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Enums\ERPTables;

return new class extends Migration
{
    public function up(): void
    {
        $sales_order_lines_table = ERPTables::SalesOrderLines->value;
        Schema::create($sales_order_lines_table, function (Blueprint $table) use ($sales_order_lines_table): void {
            $table->id();
            $table->foreignId('sales_order_id')
                ->constrained(ERPTables::SalesOrders->value, 'id', "{$sales_order_lines_table}_sales_order_id_FK")
                ->cascadeOnDelete();
            $table->foreignId('quotation_item_id')
                ->nullable()
                ->constrained(ERPTables::QuotationItems->value, 'id', "{$sales_order_lines_table}_quotation_item_id_FK")
                ->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('qty_ordered')->default(1);
            $table->unsignedInteger('qty_delivered')->default(0);
            $table->unsignedInteger('qty_invoiced')->default(0);
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->enum('status', array_map(
                static fn (SalesOrderLineStatus $s): string => $s->value,
                SalesOrderLineStatus::cases(),
            ))->default(SalesOrderLineStatus::Open->value)->index("{$sales_order_lines_table}_status_IDX");

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
                hasLocks: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::SalesOrderLines->value);
    }
};
