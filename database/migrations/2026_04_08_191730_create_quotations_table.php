<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Casts\QuoteStatus;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers', 'id', 'quotations_customer_id_FK')->restrictOnDelete()->comment('The customer that the quotation belongs to');
            $table->char('currency', 3)->default('EUR')->comment('ISO 4217 for document amounts');
            $table->text('notes')->nullable(true)->comment('The notes of the quotation');
            $table->enum('status', QuoteStatus::cases())->nullable(false)->default(QuoteStatus::DRAFT->value)->index('quotations_status_IDX')->comment('The status of the quotation');
            $table->unsignedTinyInteger('version')->default(0)->comment('The revision number of the quotation');

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
        Schema::dropIfExists('quotations');
    }
};
