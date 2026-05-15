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
            $table->string('invoice_type', 32)->default('invoice')->after('direction');
            $table->foreignId('credited_invoice_id')->nullable()->after('invoice_type')
                ->constrained(ERPTables::Invoices->value, 'id', "{$invoices_table}_credited_invoice_FK")
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $invoices_table = ERPTables::Invoices->value;
        Schema::table($invoices_table, function (Blueprint $table) use ($invoices_table): void {
            $table->dropForeign("{$invoices_table}_credited_invoice_FK");
            $table->dropColumn(['credited_invoice_id', 'invoice_type']);
        });
    }
};
