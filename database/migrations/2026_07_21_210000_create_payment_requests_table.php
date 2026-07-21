<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\PaymentRequestStatus;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = ERPTables::PaymentRequests->value;
        Schema::create($table_name, function (Blueprint $table) use ($table_name): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('party_id')->nullable()->constrained(ERPTables::Parties->value, 'id', "{$table_name}_party_FK")->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained(CoreTables::Users->value, 'id', "{$table_name}_user_FK")->restrictOnDelete();
            $table->foreignId('partner_pool_id')->nullable()->constrained(ERPTables::PartnerPools->value, 'id', "{$table_name}_pool_FK")->restrictOnDelete();
            $table->foreignId('pool_transaction_id')->nullable()->unique()->constrained(ERPTables::PoolTransactions->value, 'id', "{$table_name}_transaction_FK")->restrictOnDelete();
            $table->decimal('amount', 15, 4);
            $table->char('currency', 3);
            $table->date('due_on')->nullable();
            $table->enum('status', PaymentRequestStatus::values())->default(PaymentRequestStatus::Draft->value);
            $table->string('provider_code')->default('stub');
            $table->string('external_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('description')->nullable();
            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
            $table->index(['company_id', 'status'], "{$table_name}_company_status_IDX");
        });
        ERPMigrateUtils::positiveCheck($table_name, 'pr_amount_pos_ck', 'amount');
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::PaymentRequests->value);
    }
};
