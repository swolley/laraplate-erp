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
        $delivery_notes_table = ERPTables::DeliveryNotes->value;
        Schema::create($delivery_notes_table, function (Blueprint $table) use ($delivery_notes_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('sales_order_id')
                ->nullable()
                ->constrained(ERPTables::SalesOrders->value, 'id', "{$delivery_notes_table}_sales_order_id_FK")
                ->nullOnDelete();
            $table->string('reference', 64)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('inventory_posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('cogs_journal_entry_id')
                ->nullable()
                ->constrained(ERPTables::JournalEntries->value, 'id', "{$delivery_notes_table}_cogs_journal_entry_id_FK")
                ->nullOnDelete();

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
        Schema::dropIfExists(ERPTables::DeliveryNotes->value);
    }
};
