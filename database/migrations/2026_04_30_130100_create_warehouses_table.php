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
    public function up(): void
    {
        $warehouses_table = ERPTables::Warehouses->value;
        Schema::create($warehouses_table, function (Blueprint $table) use ($warehouses_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->string('name');
            $table->string('code', 32);
            $table->foreignId('site_id')
                ->nullable()
                ->constrained(ERPTables::Sites->value, 'id', "{$warehouses_table}_site_id_FK")
                ->nullOnDelete();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
            $table->unique(['company_id', 'code'], "{$warehouses_table}_company_code_un");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::Warehouses->value);
    }
};
