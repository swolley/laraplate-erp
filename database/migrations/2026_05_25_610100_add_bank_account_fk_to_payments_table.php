<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Enums\ERPTables;

return new class extends Migration
{
    public function up(): void
    {
        $payments_table = ERPTables::Payments->value;

        Schema::table($payments_table, function (Blueprint $table) use ($payments_table): void {
            $table->foreign('bank_account_id', "{$payments_table}_bank_account_id_FK")
                ->references('id')
                ->on(ERPTables::BankAccounts->value)
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table(ERPTables::Payments->value, function (Blueprint $table): void {
            $table->dropForeign(ERPTables::Payments->value . '_bank_account_id_FK');
        });
    }
};
