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
        Schema::table(ERPTables::Companies->value, function (Blueprint $table): void {
            $table->string('fiscal_regime', 4)->nullable()->comment('FatturaPA RegimeFiscale code, e.g. RF01');
            $table->string('legal_address_line')->nullable();
            $table->string('legal_postal_code', 16)->nullable();
            $table->string('legal_city', 128)->nullable();
            $table->string('legal_province', 8)->nullable();
            $table->string('legal_country', 2)->nullable();
            $table->string('rea_office', 8)->nullable();
            $table->string('rea_number', 32)->nullable();
            $table->decimal('share_capital', 15, 2)->nullable();
            $table->boolean('sole_shareholder')->nullable();
            $table->string('liquidation_status', 2)->nullable()->comment('FatturaPA StatoLiquidazione code');
        });

        $parties_table = ERPTables::Parties->value;
        Schema::table($parties_table, function (Blueprint $table) use ($parties_table): void {
            $table->string('tax_id', 32)->nullable()->index("{$parties_table}_tax_id_idx");
            $table->string('vat_number', 32)->nullable()->index("{$parties_table}_vat_number_idx");
            $table->string('fiscal_country', 2)->nullable();
            $table->string('address_line')->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('province', 8)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('einvoice_recipient_code', 7)->nullable()->index("{$parties_table}_sdi_code_idx");
            $table->string('einvoice_pec_email')->nullable();
        });

        Schema::table(ERPTables::Invoices->value, function (Blueprint $table): void {
            $table->string('einvoice_transmission_format', 5)->nullable()->comment('FatturaPA transmission format, e.g. FPR12 or FPA12');
            $table->string('einvoice_recipient_code', 7)->nullable();
            $table->string('einvoice_pec_email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(ERPTables::Invoices->value, function (Blueprint $table): void {
            $table->dropColumn([
                'einvoice_transmission_format',
                'einvoice_recipient_code',
                'einvoice_pec_email',
            ]);
        });

        Schema::table(ERPTables::Parties->value, function (Blueprint $table): void {
            $table->dropColumn([
                'tax_id',
                'vat_number',
                'fiscal_country',
                'address_line',
                'postal_code',
                'city',
                'province',
                'country',
                'einvoice_recipient_code',
                'einvoice_pec_email',
            ]);
        });

        Schema::table(ERPTables::Companies->value, function (Blueprint $table): void {
            $table->dropColumn([
                'fiscal_regime',
                'legal_address_line',
                'legal_postal_code',
                'legal_city',
                'legal_province',
                'legal_country',
                'rea_office',
                'rea_number',
                'share_capital',
                'sole_shareholder',
                'liquidation_status',
            ]);
        });
    }
};
