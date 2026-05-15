<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $payment_schedule_lines_table = ERPTables::PaymentScheduleLines->value;
        Schema::create($payment_schedule_lines_table, function (Blueprint $table) use ($payment_schedule_lines_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('invoice_id')
                ->constrained(ERPTables::Invoices->value, 'id', "{$payment_schedule_lines_table}_invoice_id_FK")
                ->cascadeOnDelete();
            $table->date('due_date')->index("{$payment_schedule_lines_table}_due_date_idx");
            ERPMigrateUtils::moneyColumns($table);
            $table->decimal('paid_amount_doc', 15, 4)->default(0);
            $table->decimal('paid_amount_local', 15, 4)->default(0);
            $table->enum('status', PaymentScheduleStatus::values())->default(PaymentScheduleStatus::Open->value)->index("{$payment_schedule_lines_table}_status_IDX");
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
        Schema::dropIfExists(ERPTables::PaymentScheduleLines->value);
    }
};
