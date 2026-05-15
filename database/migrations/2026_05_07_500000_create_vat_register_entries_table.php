<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $vat_register_entries_table = ERPTables::VatRegisterEntries->value;
        Schema::create($vat_register_entries_table, function (Blueprint $table) use ($vat_register_entries_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('invoice_id')
                ->constrained(ERPTables::Invoices->value, 'id', "{$vat_register_entries_table}_invoice_id_FK")
                ->cascadeOnDelete();
            $table->string('register_type', 16);
            $table->unsignedInteger('protocol_number');
            $table->date('registration_date')->index();
            $table->foreignId('fiscal_year_id')
                ->constrained(ERPTables::FiscalYears->value, 'id', "{$vat_register_entries_table}_fiscal_year_id_FK")
                ->restrictOnDelete();
            $table->foreignId('tax_code_id')
                ->constrained(ERPTables::TaxCodes->value, 'id', "{$vat_register_entries_table}_tax_code_id_FK")
                ->restrictOnDelete();
            $table->decimal('taxable_amount', 15, 4);
            $table->decimal('tax_amount', 15, 4);

            $table->unique(
                ['company_id', 'register_type', 'fiscal_year_id', 'protocol_number'],
                "{$vat_register_entries_table}_protocol_unique",
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
        Schema::dropIfExists(ERPTables::VatRegisterEntries->value);
    }
};
