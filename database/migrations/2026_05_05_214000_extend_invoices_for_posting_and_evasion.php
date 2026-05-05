<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->after('posted_at')
                ->constrained('journal_entries')
                ->nullOnDelete();
        });

        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->foreignId('sales_order_line_id')
                ->nullable()
                ->after('tax_code_id')
                ->constrained('sales_order_lines')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sales_order_line_id');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('journal_entry_id');
        });
    }
};
