<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\BankStatementLineStatus;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $bank_statement_lines_table = ERPTables::BankStatementLines->value;

        Schema::create($bank_statement_lines_table, function (Blueprint $table) use ($bank_statement_lines_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('bank_statement_id')
                ->constrained(ERPTables::BankStatements->value, 'id', "{$bank_statement_lines_table}_bank_statement_id_FK")
                ->cascadeOnDelete();
            $table->foreignId('matched_payment_id')
                ->nullable()
                ->constrained(ERPTables::Payments->value, 'id', "{$bank_statement_lines_table}_matched_payment_id_FK")
                ->nullOnDelete();
            $table->date('booked_at');
            $table->date('value_at')->nullable();
            $table->string('reference', 128)->nullable();
            $table->text('description')->nullable();
            ERPMigrateUtils::moneyColumns($table);
            $table->enum('status', array_map(
                static fn (BankStatementLineStatus $status): string => $status->value,
                BankStatementLineStatus::cases(),
            ))->default(BankStatementLineStatus::Imported->value);
            $table->json('raw_payload')->nullable();
            $table->index(['company_id', 'booked_at'], "{$bank_statement_lines_table}_company_booked_idx");

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::BankStatementLines->value);
    }
};
