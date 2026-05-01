<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->foreignId('item_id')
                ->nullable()
                ->after('quotation_item_id')
                ->constrained('items', 'id', 'sales_order_lines_item_id_FK')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->dropForeign('sales_order_lines_item_id_FK');
            $table->dropColumn('item_id');
        });
    }
};
