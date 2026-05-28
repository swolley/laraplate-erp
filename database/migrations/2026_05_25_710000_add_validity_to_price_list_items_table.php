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
        Schema::table(ERPTables::PriceListItems->value, function (Blueprint $table): void {
            MigrateUtils::timestamps($table, hasValidity: true, isValidityRequired: false);
        });
    }

    public function down(): void
    {
        Schema::table(ERPTables::PriceListItems->value, function (Blueprint $table): void {
            MigrateUtils::dropTimestamps($table, hasCreateUpdate: false, hasValidity: true);
        });
    }
};
