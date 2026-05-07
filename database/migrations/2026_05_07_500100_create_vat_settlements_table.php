<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_settlements', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('fiscal_period_id')
                ->constrained('fiscal_periods', 'id', 'vat_settlements_fiscal_period_id_FK')
                ->restrictOnDelete();
            $table->decimal('vat_sales', 15, 4)->default(0);
            $table->decimal('vat_purchases', 15, 4)->default(0);
            $table->decimal('previous_credit', 15, 4)->default(0);
            $table->decimal('settlement_amount', 15, 4)->default(0);
            $table->string('status', 16)->default('draft');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable();

            $table->unique(
                ['company_id', 'fiscal_period_id'],
                'vat_settlement_period_unique',
            );

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_settlements');
    }
};
