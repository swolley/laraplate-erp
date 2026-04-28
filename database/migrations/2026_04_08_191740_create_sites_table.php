<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Helpers\BusinessMigrateUtils;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table): void {
            $table->id();
            BusinessMigrateUtils::companyForeign($table);
            $table->string('name')->comment('The name of the site');
            $table->foreignId('place_id')->constrained('places', 'id', 'sites_place_id_FK')->restrictOnDelete()->comment('The place that the site belongs to');

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
        Schema::dropIfExists('sites');
    }
};
