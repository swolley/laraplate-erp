<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('customer_id')
                ->constrained('customers', 'id', 'sales_orders_customer_id_FK')
                ->restrictOnDelete();
            $table->foreignId('quotation_id')
                ->nullable()
                ->constrained('quotations', 'id', 'sales_orders_quotation_id_FK')
                ->nullOnDelete();
            $table->foreignId('project_id')
                ->nullable()
                ->constrained('projects', 'id', 'sales_orders_project_id_FK')
                ->nullOnDelete();
            $table->foreignId('amends_sales_order_id')
                ->nullable()
                ->constrained('sales_orders', 'id', 'sales_orders_amends_sales_order_id_FK')
                ->nullOnDelete();
            $table->string('reference', 64)->nullable()->comment('Human-friendly reference until fiscal numbering is bound');
            $table->char('currency', 3)->default('EUR');
            $table->enum('status', array_map(
                static fn (SalesOrderStatus $s): string => $s->value,
                SalesOrderStatus::cases(),
            ))->default(SalesOrderStatus::DRAFT->value)->index('sales_orders_status_IDX');
            $table->text('notes')->nullable();

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
                hasLocks: true,
                hasValidity: true,
                isValidityRequired: false,
            );

            $table->index(['company_id', 'customer_id'], 'sales_orders_company_customer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
