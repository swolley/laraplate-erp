<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders', 'id', 'purchase_order_lines_purchase_order_id_FK')
                ->cascadeOnDelete();
            $table->foreignId('item_id')
                ->nullable()
                ->constrained('items', 'id', 'purchase_order_lines_item_id_FK')
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
        Schema::dropIfExists('purchase_order_lines');
    }
};
