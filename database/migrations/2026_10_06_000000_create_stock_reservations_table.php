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
        $stock_reservations_table = ERPTables::StockReservations->value;
        Schema::create($stock_reservations_table, function (Blueprint $table) use ($stock_reservations_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('item_id')
                ->constrained(ERPTables::Items->value, 'id', "{$stock_reservations_table}_item_id_FK")
                ->restrictOnDelete();
            MigrateUtils::prefixIndex($table, 'item_id');
            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained(ERPTables::Warehouses->value, 'id', "{$stock_reservations_table}_warehouse_id_FK")
                ->restrictOnDelete();
            MigrateUtils::prefixIndex($table, 'warehouse_id');
            $table->string('source_type')->comment('Opaque label of the reserving document; never resolved by ERP');
            $table->unsignedBigInteger('source_id')->comment('Opaque id of the reserving document within source_type');
            $table->decimal('quantity', 15, 4)->comment('Always positive; the quantity held back from availability');
            $table->string('state')->comment('soft | hard | consumed | released');
            $table->timestamp('expires_at')->nullable()->comment('When a live reservation lapses; null means it does not expire');

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);

            $table->index(['company_id', 'item_id', 'state'], "{$stock_reservations_table}_company_item_state_idx");
            $table->index(['source_type', 'source_id'], "{$stock_reservations_table}_source_idx");
        });

        ERPMigrateUtils::positiveCheck($stock_reservations_table, 'sr_qty_pos_ck', 'quantity');
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::StockReservations->value);
    }
};
