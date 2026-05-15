<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
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
        $sites_table = ERPTables::Sites->value;
        Schema::create($sites_table, function (Blueprint $table) use ($sites_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->string('name')->comment('The name of the site');
            $table->foreignId('place_id')->constrained(CoreTables::Places->value, 'id', "{$sites_table}_place_id_FK")->restrictOnDelete()->comment('The place that the site belongs to');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
                hasValidity: true,
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ERPTables::Sites->value);
    }
};
