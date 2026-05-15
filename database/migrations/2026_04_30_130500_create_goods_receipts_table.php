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
        $goods_receipts_table = ERPTables::GoodsReceipts->value;
        Schema::create($goods_receipts_table, function (Blueprint $table) use ($goods_receipts_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('purchase_order_id')
                ->nullable()
                ->constrained(ERPTables::PurchaseOrders->value, 'id', "{$goods_receipts_table}_purchase_order_id_FK")
                ->nullOnDelete();
            $table->string('reference', 64)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('inventory_posted_at')->nullable();
            $table->text('notes')->nullable();

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
                hasLocks: true,
                hasValidity: true,
                isValidityRequired: false,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::GoodsReceipts->value);
    }
};
