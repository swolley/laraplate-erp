<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')
                ->constrained('payments', 'id', 'payment_allocations_payment_id_FK')
                ->cascadeOnDelete();
            $table->foreignId('payment_schedule_line_id')
                ->constrained('payment_schedule_lines', 'id', 'payment_allocations_psl_id_FK')
                ->restrictOnDelete();
            $table->decimal('allocated_amount_doc', 15, 4);
            $table->decimal('allocated_amount_local', 15, 4);

            $table->unique(
                ['payment_id', 'payment_schedule_line_id'],
                'payment_allocations_payment_psl_unique',
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
        Schema::dropIfExists('payment_allocations');
    }
};
