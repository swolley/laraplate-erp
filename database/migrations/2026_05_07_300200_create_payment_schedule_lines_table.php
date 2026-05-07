<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_schedule_lines', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('invoice_id')
                ->constrained('invoices', 'id', 'payment_schedule_lines_invoice_id_FK')
                ->cascadeOnDelete();
            $table->date('due_date')->index('payment_schedule_lines_due_date_idx');
            ERPMigrateUtils::moneyColumns($table);
            $table->decimal('paid_amount_doc', 15, 4)->default(0);
            $table->decimal('paid_amount_local', 15, 4)->default(0);
            $table->string('status', 32)->default(PaymentScheduleStatus::Open->value);
            $table->timestamp('paid_at')->nullable();

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_schedule_lines');
    }
};
