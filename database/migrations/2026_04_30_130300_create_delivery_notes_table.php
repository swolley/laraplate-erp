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
        Schema::create('delivery_notes', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('sales_order_id')
                ->nullable()
                ->constrained('sales_orders', 'id', 'delivery_notes_sales_order_id_FK')
                ->nullOnDelete();
            $table->string('reference', 64)->nullable();
            $table->timestamp('delivered_at')->nullable();
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
        Schema::dropIfExists('delivery_notes');
    }
};
