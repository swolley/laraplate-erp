<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('customer_id')
                ->constrained('customers', 'id', 'purchase_orders_customer_id_FK')
                ->restrictOnDelete();
            $table->string('reference', 64)->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->string('status', 32)->default('draft');
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
        Schema::dropIfExists('purchase_orders');
    }
};
