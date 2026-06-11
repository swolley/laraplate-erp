<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $supplier_returns_table = ERPTables::SupplierReturns->value;

        Schema::create($supplier_returns_table, function (Blueprint $table) use ($supplier_returns_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('party_id')->constrained(ERPTables::Parties->value, 'id', "{$supplier_returns_table}_party_id_FK")->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained(ERPTables::PurchaseOrders->value, 'id', "{$supplier_returns_table}_purchase_order_id_FK")->nullOnDelete();
            $table->foreignId('debit_note_invoice_id')->nullable()->constrained(ERPTables::Invoices->value, 'id', "{$supplier_returns_table}_debit_note_invoice_id_FK")->nullOnDelete();
            $table->foreignId('delivery_note_id')->nullable()->constrained(ERPTables::DeliveryNotes->value, 'id', "{$supplier_returns_table}_delivery_note_id_FK")->nullOnDelete();
            $table->string('reference', 64)->nullable();
            $table->enum('status', array_map(static fn (ReturnStatus $status): string => $status->value, ReturnStatus::cases()))->default(ReturnStatus::Draft->value);
            $table->timestamp('processed_at')->nullable();
            $table->text('notes')->nullable();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::SupplierReturns->value);
    }
};
