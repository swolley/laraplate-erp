<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;

return new class extends Migration
{
    public function up(): void
    {
        $payment_allocations_table = ERPTables::PaymentAllocations->value;
        Schema::create($payment_allocations_table, function (Blueprint $table) use ($payment_allocations_table): void {
            $table->id();
            $table->foreignId('payment_id')
                ->constrained(ERPTables::Payments->value, 'id', "{$payment_allocations_table}_payment_id_FK")
                ->cascadeOnDelete();
            $table->foreignId('payment_schedule_line_id')
                ->constrained(ERPTables::PaymentScheduleLines->value, 'id', "{$payment_allocations_table}_psl_id_FK")
                ->restrictOnDelete();
            $table->decimal('allocated_amount_doc', 15, 4);
            $table->decimal('allocated_amount_local', 15, 4);

            $table->unique(
                ['payment_id', 'payment_schedule_line_id'],
                "{$payment_allocations_table}_payment_psl_unique",
            );

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: false,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::PaymentAllocations->value);
    }
};
