<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->foreignId('opportunity_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('opportunities', 'id', 'quotations_opportunity_id_FK')
                ->nullOnDelete()
                ->comment('Originating CRM opportunity when applicable');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropForeign('quotations_opportunity_id_FK');
            $table->dropColumn('opportunity_id');
        });
    }
};
