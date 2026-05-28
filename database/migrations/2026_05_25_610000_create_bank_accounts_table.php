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
        $bank_accounts_table = ERPTables::BankAccounts->value;

        Schema::create($bank_accounts_table, function (Blueprint $table) use ($bank_accounts_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->string('name');
            $table->string('iban', 34)->nullable();
            $table->string('account_no', 64)->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->boolean('is_active')->default(true);
            $table->unique(['company_id', 'iban'], "{$bank_accounts_table}_company_iban_UN");

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::BankAccounts->value);
    }
};
