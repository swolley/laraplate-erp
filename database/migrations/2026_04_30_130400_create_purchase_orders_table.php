<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\PurchaseOrderStatus;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $purchase_orders_table = ERPTables::PurchaseOrders->value;
        Schema::create($purchase_orders_table, function (Blueprint $table) use ($purchase_orders_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('party_id')
                ->constrained(ERPTables::Parties->value, 'id', "{$purchase_orders_table}_party_id_FK")
                ->restrictOnDelete();
            $table->string('reference', 64)->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->enum('status', PurchaseOrderStatus::values())->default(PurchaseOrderStatus::Draft->value)->index("{$purchase_orders_table}_status_IDX");
            $table->timestamp('ordered_at')->nullable();

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
        Schema::dropIfExists(ERPTables::PurchaseOrders->value);
    }
};
