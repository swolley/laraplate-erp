<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\SalesOrderLineStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_id')
                ->constrained('sales_orders', 'id', 'sales_order_lines_sales_order_id_FK')
                ->cascadeOnDelete();
            $table->foreignId('quotation_item_id')
                ->nullable()
                ->constrained('quotations_items', 'id', 'sales_order_lines_quotation_item_id_FK')
                ->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('qty_ordered')->default(1);
            $table->unsignedInteger('qty_delivered')->default(0);
            $table->unsignedInteger('qty_invoiced')->default(0);
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->enum('status', array_map(
                static fn (SalesOrderLineStatus $s): string => $s->value,
                SalesOrderLineStatus::cases(),
            ))->default(SalesOrderLineStatus::OPEN->value)->index('sales_order_lines_status_IDX');

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
        Schema::dropIfExists('sales_order_lines');
    }
};
