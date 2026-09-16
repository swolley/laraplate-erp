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
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $parties_table = ERPTables::Parties->value;
        Schema::create($parties_table, function (Blueprint $table) use ($parties_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->string('name')->comment('The name of the party');
            $table->boolean('is_customer')->default(true)->comment('Whether the party is a customer');
            $table->boolean('is_supplier')->default(false)->comment('Whether the party is a supplier');
            $table->boolean('is_active')->default(true)->index("{$parties_table}_is_active_IDX")->comment('Whether the party is active');
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

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ERPTables::Parties->value);
    }
};
