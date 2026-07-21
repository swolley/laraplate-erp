<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tasks_table = ERPTables::Tasks->value;
        Schema::create($tasks_table, function (Blueprint $table) use ($tasks_table): void {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained(ERPTables::Projects->value, 'id', "{$tasks_table}_project_id_FK")->nullOnDelete()->comment('The project that the task belongs to');
            $table->foreignId('site_id')->nullable()->constrained(ERPTables::Sites->value, 'id', "{$tasks_table}_site_id_FK")->nullOnDelete()->comment('The site that the task belongs to');
            $table->foreignId('taxonomy_id')->constrained(CoreTables::Taxonomies->value, 'id', "{$tasks_table}_taxonomy_id_FK")->restrictOnDelete()->comment('Activity type node in taxonomies (EntityType activities)');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
                hasValidity: true,
                isValidityRequired: true,
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ERPTables::Tasks->value);
    }
};
