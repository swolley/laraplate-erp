<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $sales_orders_table = ERPTables::SalesOrders->value;
        Schema::create($sales_orders_table, function (Blueprint $table) use ($sales_orders_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('party_id')
                ->constrained(ERPTables::Parties->value, 'id', "{$sales_orders_table}_party_id_FK")
                ->restrictOnDelete();
            $table->foreignId('quotation_id')
                ->nullable()
                ->constrained(ERPTables::Quotations->value, 'id', "{$sales_orders_table}_quotation_id_FK")
                ->nullOnDelete();
            $table->foreignId('project_id')
                ->nullable()
                ->constrained(ERPTables::Projects->value, 'id', "{$sales_orders_table}_project_id_FK")
                ->nullOnDelete();
            $table->foreignId('amends_sales_order_id')
                ->nullable()
                ->constrained($sales_orders_table, 'id', "{$sales_orders_table}_amends_sales_order_id_FK")
                ->nullOnDelete();
            $table->string('reference', 64)->nullable()->comment('Human-friendly reference until fiscal numbering is bound');
            $table->char('currency', 3)->default('EUR');
            $table->enum('status', array_map(
                static fn (SalesOrderStatus $s): string => $s->value,
                SalesOrderStatus::cases(),
            ))->default(SalesOrderStatus::Draft->value)->index("{$sales_orders_table}_status_IDX");
            $table->text('notes')->nullable();

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
                hasLocks: true,
                hasValidity: true,
                isValidityRequired: false,
            );

            $table->index(['company_id', 'party_id'], "{$sales_orders_table}_company_party_idx");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::SalesOrders->value);
    }
};
