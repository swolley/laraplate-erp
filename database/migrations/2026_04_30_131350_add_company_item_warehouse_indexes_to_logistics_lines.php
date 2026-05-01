<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_note_lines', function (Blueprint $table): void {
            $table->index(
                ['company_id', 'item_id', 'warehouse_id'],
                'delivery_note_lines_company_item_wh_idx',
            );
        });

        Schema::table('goods_receipt_lines', function (Blueprint $table): void {
            $table->index(
                ['company_id', 'item_id', 'warehouse_id'],
                'goods_receipt_lines_company_item_wh_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('delivery_note_lines', function (Blueprint $table): void {
            $table->dropIndex('delivery_note_lines_company_item_wh_idx');
        });

        Schema::table('goods_receipt_lines', function (Blueprint $table): void {
            $table->dropIndex('goods_receipt_lines_company_item_wh_idx');
        });
    }
};
