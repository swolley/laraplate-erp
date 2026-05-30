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
        $invoices_table = ERPTables::Invoices->value;
        Schema::table($invoices_table, function (Blueprint $table) use ($invoices_table): void {
            $table->foreignId('party_id')
                ->nullable()
                ->after('company_id')
                ->constrained(ERPTables::Parties->value, 'id', "{$invoices_table}_party_id_FK")
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $invoices_table = ERPTables::Invoices->value;
        Schema::table($invoices_table, function (Blueprint $table) use ($invoices_table): void {
            $table->dropForeign("{$invoices_table}_party_id_FK");
            $table->dropColumn('party_id');
        });
    }
};
