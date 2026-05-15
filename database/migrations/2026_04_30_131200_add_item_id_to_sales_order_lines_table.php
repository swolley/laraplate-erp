<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Enums\ERPTables;

return new class extends Migration
{
    public function up(): void
    {
        $sales_order_lines_table = ERPTables::SalesOrderLines->value;
        Schema::table($sales_order_lines_table, function (Blueprint $table) use ($sales_order_lines_table): void {
            $table->foreignId('item_id')
                ->nullable()
                ->after('quotation_item_id')
                ->constrained(ERPTables::Items->value, 'id', "{$sales_order_lines_table}_item_id_FK")
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $sales_order_lines_table = ERPTables::SalesOrderLines->value;
        Schema::table($sales_order_lines_table, function (Blueprint $table) use ($sales_order_lines_table): void {
            $table->dropForeign("{$sales_order_lines_table}_item_id_FK");
            $table->dropColumn('item_id');
        });
    }
};
