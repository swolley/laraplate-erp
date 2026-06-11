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
        $return_orders_table = ERPTables::ReturnOrders->value;

        Schema::create($return_orders_table, function (Blueprint $table) use ($return_orders_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('party_id')->constrained(ERPTables::Parties->value, 'id', "{$return_orders_table}_party_id_FK")->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained(ERPTables::Invoices->value, 'id', "{$return_orders_table}_invoice_id_FK")->nullOnDelete();
            $table->foreignId('credit_note_invoice_id')->nullable()->constrained(ERPTables::Invoices->value, 'id', "{$return_orders_table}_credit_note_invoice_id_FK")->nullOnDelete();
            $table->foreignId('delivery_note_id')->nullable()->constrained(ERPTables::DeliveryNotes->value, 'id', "{$return_orders_table}_delivery_note_id_FK")->nullOnDelete();
            $table->string('reference', 64)->nullable();
            $table->enum('status', array_map(static fn (ReturnStatus $status): string => $status->value, ReturnStatus::cases()))->default(ReturnStatus::Draft->value);
            $table->timestamp('processed_at')->nullable();
            $table->text('notes')->nullable();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::ReturnOrders->value);
    }
};
