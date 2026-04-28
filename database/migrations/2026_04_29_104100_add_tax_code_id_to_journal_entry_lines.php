<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->foreignId('tax_code_id')
                ->nullable()
                ->after('account_id')
                ->constrained('tax_codes', 'id', 'journal_entry_lines_tax_code_id_FK')
                ->nullOnDelete()
                ->comment('Optional FK; fiscal strings remain the immutable posting snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->dropForeign('journal_entry_lines_tax_code_id_FK');
            $table->dropColumn('tax_code_id');
        });
    }
};
