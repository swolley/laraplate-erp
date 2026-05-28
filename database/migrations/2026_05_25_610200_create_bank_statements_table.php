<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $bank_statements_table = ERPTables::BankStatements->value;

        Schema::create($bank_statements_table, function (Blueprint $table) use ($bank_statements_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('bank_account_id')
                ->constrained(ERPTables::BankAccounts->value, 'id', "{$bank_statements_table}_bank_account_id_FK")
                ->restrictOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->string('source_filename')->nullable();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::BankStatements->value);
    }
};
