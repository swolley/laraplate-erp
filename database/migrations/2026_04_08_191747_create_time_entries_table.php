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
        Schema::create('time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users', 'id', 'time_entries_user_id_FK')->restrictOnDelete();
            $table->foreignId('taxonomy_id')->constrained('taxonomies', 'id', 'time_entries_taxonomy_id_FK')->restrictOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks', 'id', 'time_entries_task_id_FK')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects', 'id', 'time_entries_project_id_FK')->nullOnDelete();
            $table->foreignId('quotation_item_id')->nullable()->constrained('quotations_items', 'id', 'time_entries_quotation_item_id_FK')->nullOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable()->comment('Null while the session is still open');

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
        Schema::dropIfExists('time_entries');
    }
};
