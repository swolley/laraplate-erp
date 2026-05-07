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
        Schema::create('vat_register_entries', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('invoice_id')
                ->constrained('invoices', 'id', 'vat_reg_entries_invoice_id_FK')
                ->cascadeOnDelete();
            $table->string('register_type', 16);
            $table->unsignedInteger('protocol_number');
            $table->date('registration_date')->index();
            $table->foreignId('fiscal_year_id')
                ->constrained('fiscal_years', 'id', 'vat_reg_entries_fiscal_year_id_FK')
                ->restrictOnDelete();
            $table->foreignId('tax_code_id')
                ->constrained('tax_codes', 'id', 'vat_reg_entries_tax_code_id_FK')
                ->restrictOnDelete();
            $table->decimal('taxable_amount', 15, 4);
            $table->decimal('tax_amount', 15, 4);

            $table->unique(
                ['company_id', 'register_type', 'fiscal_year_id', 'protocol_number'],
                'vat_reg_protocol_unique',
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
        Schema::dropIfExists('vat_register_entries');
    }
};
