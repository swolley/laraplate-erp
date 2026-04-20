<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Casts\ProjectStatus;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers', 'id', 'projects_customer_id_FK')->restrictOnDelete()->comment('The customer that the project belongs to');
            $table->foreignId('quotation_id')->constrained('quotations', 'id', 'projects_quotation_id_FK')->nullable(true)->setNullOnDelete()->comment('The quotation that the project belongs to');
            $table->string('name')->comment('The name of the project');
            $table->text('description')->nullable(true)->comment('The description of the project');
            $table->enum('status', ProjectStatus::cases())->nullable(false)->default(ProjectStatus::ACTIVE->value)->index('projects_status_IDX')->comment('The status of the project');
            $table->unsignedTinyInteger('version')->default(0)->comment('The revision number of the project');

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
        Schema::dropIfExists('projects');
    }
};
