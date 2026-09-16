<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;

return new class extends Migration
{
    public function up(): void
    {
        $companies_table = ERPTables::Companies->value;
        Schema::create($companies_table, function (Blueprint $table) use ($companies_table): void {
            $table->id();
            $table->string('slug')->unique()->comment('Stable machine-friendly identifier (used in URLs, configs, etc.)');
            $table->string('name')->comment('Human-friendly display name');
            $table->string('legal_name')->nullable()->comment('Registered legal name; falls back to name when null');
            $table->string('tax_id')->nullable()->index()->comment('Italian P.IVA / EU VAT number / equivalent fiscal id');
            $table->string('fiscal_country', 2)->default('IT')->comment('ISO 3166-1 alpha-2 country code driving fiscal rules');
            $table->char('default_currency', 3)->default('EUR')->comment('ISO 4217 functional currency used as amount_local for journal balancing');
            $table->json('settings')->nullable()->comment('Free-form per-company settings (logo, defaults, etc.)');
            $table->boolean('is_default')->default(false)->index("{$companies_table}_is_default_IDX")->comment('At most one company per environment can be the default tenant');
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

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::Companies->value);
    }
};
