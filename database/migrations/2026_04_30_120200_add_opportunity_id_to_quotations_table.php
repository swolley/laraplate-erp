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
        $quotations_table = ERPTables::Quotations->value;
        Schema::table($quotations_table, function (Blueprint $table) use ($quotations_table): void {
            $table->foreignId('opportunity_id')
                ->nullable()
                ->after('party_id')
                ->constrained(ERPTables::Opportunities->value, 'id', "{$quotations_table}_opportunity_id_FK")
                ->nullOnDelete()
                ->comment('Originating CRM opportunity when applicable');
        });
    }

    public function down(): void
    {
        $quotations_table = ERPTables::Quotations->value;
        Schema::table($quotations_table, function (Blueprint $table) use ($quotations_table): void {
            $table->dropForeign("{$quotations_table}_opportunity_id_FK");
            $table->dropColumn('opportunity_id');
        });
    }
};
