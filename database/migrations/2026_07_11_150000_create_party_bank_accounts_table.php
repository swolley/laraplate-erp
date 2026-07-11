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
        $party_bank_accounts_table = ERPTables::PartyBankAccounts->value;

        Schema::create($party_bank_accounts_table, function (Blueprint $table) use ($party_bank_accounts_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('party_id')
                ->constrained(ERPTables::Parties->value, 'id', "{$party_bank_accounts_table}_party_id_FK")
                ->cascadeOnDelete();
            $table->string('beneficiary_name');
            $table->string('iban', 34);
            $table->string('bic', 11)->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->index(['company_id', 'party_id'], "{$party_bank_accounts_table}_company_party_idx");
            $table->unique(['company_id', 'party_id', 'iban'], "{$party_bank_accounts_table}_party_iban_UN");

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::PartyBankAccounts->value);
    }
};
