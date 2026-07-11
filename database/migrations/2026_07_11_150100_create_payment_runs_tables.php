<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\PaymentRunFormat;
use Modules\ERP\Casts\PaymentRunLineStatus;
use Modules\ERP\Casts\PaymentRunStatus;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $payment_runs_table = ERPTables::PaymentRuns->value;
        $payment_run_lines_table = ERPTables::PaymentRunLines->value;

        Schema::create($payment_runs_table, function (Blueprint $table) use ($payment_runs_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('bank_account_id')
                ->constrained(ERPTables::BankAccounts->value, 'id', "{$payment_runs_table}_bank_account_id_FK")
                ->restrictOnDelete();
            $table->date('execution_date');
            $table->char('currency', 3)->default('EUR');
            $table->decimal('total_amount_doc', 15, 4)->default(0);
            $table->decimal('total_amount_local', 15, 4)->default(0);
            $table->enum('status', PaymentRunStatus::values())->default(PaymentRunStatus::Draft->value);
            $table->enum('format', PaymentRunFormat::values())->default(PaymentRunFormat::SepaPain001->value);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->string('export_file_name')->nullable();
            $table->string('export_checksum', 128)->nullable();
            $table->index(['company_id', 'status'], "{$payment_runs_table}_company_status_idx");
            $table->index(['company_id', 'execution_date'], "{$payment_runs_table}_company_exec_idx");

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
        });

        Schema::create($payment_run_lines_table, function (Blueprint $table) use ($payment_run_lines_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('payment_run_id')
                ->constrained(ERPTables::PaymentRuns->value, 'id', "{$payment_run_lines_table}_run_id_FK")
                ->cascadeOnDelete();
            $table->foreignId('payment_schedule_line_id')
                ->constrained(ERPTables::PaymentScheduleLines->value, 'id', "{$payment_run_lines_table}_sched_line_id_FK")
                ->restrictOnDelete();
            $table->foreignId('party_id')
                ->constrained(ERPTables::Parties->value, 'id', "{$payment_run_lines_table}_party_id_FK")
                ->restrictOnDelete();
            $table->foreignId('party_bank_account_id')
                ->nullable()
                ->constrained(ERPTables::PartyBankAccounts->value, 'id', "{$payment_run_lines_table}_party_bank_id_FK")
                ->nullOnDelete();
            $table->decimal('amount_doc', 15, 4);
            $table->char('currency_doc', 3);
            $table->decimal('amount_local', 15, 4);
            $table->char('currency_local', 3);
            $table->date('due_date');
            $table->string('beneficiary_name');
            $table->string('beneficiary_iban', 34);
            $table->string('beneficiary_bic', 11)->nullable();
            $table->string('remittance_information', 140)->nullable();
            $table->enum('status', PaymentRunLineStatus::values())->default(PaymentRunLineStatus::Included->value);
            $table->unique(['payment_run_id', 'payment_schedule_line_id'], "{$payment_run_lines_table}_run_sched_UN");
            $table->index(['company_id', 'party_id'], "{$payment_run_lines_table}_company_party_idx");

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
        });

        ERPMigrateUtils::nonNegativeCheck($payment_runs_table, 'pr_total_doc_nn_ck', 'total_amount_doc');
        ERPMigrateUtils::nonNegativeCheck($payment_runs_table, 'pr_total_local_nn_ck', 'total_amount_local');
        ERPMigrateUtils::positiveCheck($payment_run_lines_table, 'prl_amount_doc_pos_ck', 'amount_doc');
        ERPMigrateUtils::positiveCheck($payment_run_lines_table, 'prl_amount_local_pos_ck', 'amount_local');
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::PaymentRunLines->value);
        Schema::dropIfExists(ERPTables::PaymentRuns->value);
    }
};
