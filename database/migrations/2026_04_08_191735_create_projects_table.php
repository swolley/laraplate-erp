<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\ProjectStatus;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $projects_table = ERPTables::Projects->value;
        Schema::create($projects_table, function (Blueprint $table) use ($projects_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('party_id')->constrained(ERPTables::Parties->value, 'id', "{$projects_table}_party_id_FK")->restrictOnDelete()->comment('The party that the project belongs to');
            $table->foreignId('quotation_id')->constrained(ERPTables::Quotations->value, 'id', "{$projects_table}_quotation_id_FK")->nullable(true)->setNullOnDelete()->comment('The quotation that the project belongs to');
            $table->string('name')->comment('The name of the project');
            $table->text('description')->nullable(true)->comment('The description of the project');
            $table->enum('status', ProjectStatus::cases())->nullable(false)->default(ProjectStatus::Active->value)->index("{$projects_table}_status_IDX")->comment('The status of the project');
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
        Schema::dropIfExists(ERPTables::Projects->value);
    }
};
