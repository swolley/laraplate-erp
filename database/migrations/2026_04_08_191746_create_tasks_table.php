<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects', 'id', 'tasks_projects_FK')->nullable(true)->setNullOnDelete()->comment('The project that the task belongs to');
            $table->foreignId('site_id')->constrained('sites', 'id', 'tasks_sites_FK')->nullable(true)->setNullOnDelete()->comment('The site that the task belongs to');
            $table->foreignId('taxonomy_id')->constrained('taxonomies', 'id', 'tasks_taxonomy_id_FK')->restrictOnDelete()->comment('Activity type node in taxonomies (EntityType activities)');

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
        Schema::dropIfExists('tasks');
    }
};
