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
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->after('posted_at')
                ->constrained(ERPTables::JournalEntries->value, 'id', "{$invoices_table}_journal_entry_id_FK")
                ->nullOnDelete();
        });

        $invoice_lines_table = ERPTables::InvoiceLines->value;
        Schema::table($invoice_lines_table, function (Blueprint $table) use ($invoice_lines_table): void {
            $table->foreignId('sales_order_line_id')
                ->nullable()
                ->after('tax_code_id')
                ->constrained(ERPTables::SalesOrderLines->value, 'id', "{$invoice_lines_table}_sales_order_line_id_FK")
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table(ERPTables::InvoiceLines->value, function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sales_order_line_id');
        });

        Schema::table(ERPTables::Invoices->value, function (Blueprint $table): void {
            $table->dropConstrainedForeignId('journal_entry_id');
        });
    }
};
