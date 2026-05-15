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
        $price_lists_table = ERPTables::PriceLists->value;
        Schema::create($price_lists_table, function (Blueprint $table) use ($price_lists_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->string('name')->comment('The name of the price list');
            $table->char('currency', 3)->default('EUR')->index("{$price_lists_table}_currency_idx")->comment('ISO 4217; line items inherit this currency');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
                hasLocks: false,
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
        Schema::dropIfExists(ERPTables::PriceLists->value);
    }
};
