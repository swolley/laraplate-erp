<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\QuoteStatus;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $quotations_table = ERPTables::Quotations->value;
        Schema::create($quotations_table, function (Blueprint $table) use ($quotations_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('party_id')->constrained(ERPTables::Parties->value, 'id', "{$quotations_table}_party_id_FK")->restrictOnDelete()->comment('The party that the quotation belongs to');
            $table->char('currency', 3)->default('EUR')->index("{$quotations_table}_currency_idx")->comment('ISO 4217 for document amounts');
            $table->text('notes')->nullable(true)->comment('The notes of the quotation');
            $table->enum('status', QuoteStatus::cases())->nullable(false)->default(QuoteStatus::Draft->value)->index("{$quotations_table}_status_IDX")->comment('The status of the quotation');
            $table->unsignedTinyInteger('version')->default(0)->comment('The revision number of the quotation');
            $table->foreignId('revises_quotation_id')
                ->nullable()
                ->unique()
                ->constrained($quotations_table, 'id', "{$quotations_table}_revises_quotation_id_FK")
                ->restrictOnDelete()
                ->comment('Immediate previous quotation in the linear revision chain');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
                hasLocks: true,
                hasValidity: true,
                isValidityRequired: false,
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ERPTables::Quotations->value);
    }
};
