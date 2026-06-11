<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

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
            $table->decimal('qty_ordered', 15, 4)->default(1);
            $table->decimal('qty_delivered', 15, 4)->default(0);
            $table->decimal('qty_invoiced', 15, 4)->default(0);
            $table->decimal('qty_returned', 15, 4)->default(0);
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

        ERPMigrateUtils::positiveCheck($sales_order_lines_table, 'sol_qo_pos_ck', 'qty_ordered');
        ERPMigrateUtils::nonNegativeCheck($sales_order_lines_table, 'sol_qd_nn_ck', 'qty_delivered');
        ERPMigrateUtils::nonNegativeCheck($sales_order_lines_table, 'sol_qi_nn_ck', 'qty_invoiced');
        ERPMigrateUtils::nonNegativeCheck($sales_order_lines_table, 'sol_qr_nn_ck', 'qty_returned');
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::SalesOrderLines->value);
    }
};
