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
        $parties_table = ERPTables::Parties->value;
        Schema::create($parties_table, function (Blueprint $table) use ($parties_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->string('name')->comment('The name of the party');
            $table->boolean('is_customer')->default(true)->comment('Whether the party is a customer');
            $table->boolean('is_supplier')->default(false)->comment('Whether the party is a supplier');
            $table->boolean('is_active')->default(true)->index("{$parties_table}_is_active_IDX")->comment('Whether the party is active');

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
        Schema::dropIfExists(ERPTables::Parties->value);
    }
};
