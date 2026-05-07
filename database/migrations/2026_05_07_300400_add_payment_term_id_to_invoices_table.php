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
            $table->foreignId('payment_term_id')
                ->nullable()
                ->after('notes')
                ->constrained('payment_terms', 'id', 'invoices_payment_term_id_FK')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign('invoices_payment_term_id_FK');
            $table->dropColumn('payment_term_id');
        });
    }
};
