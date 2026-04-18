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
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers', 'id', 'projects_customer_id_FK')->nullable(true)->setNullOnDelete();
            $table->foreignId('quotation_id')->constrained('quotations', 'id', 'projects_quotation_id_FK')->nullable(true)->setNullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ProjectStatus::cases())->nullable(false)->default(ProjectStatus::DRAFT->value);

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
