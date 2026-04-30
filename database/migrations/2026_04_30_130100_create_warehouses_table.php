<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->string('name');
            $table->string('code', 32);
            $table->foreignId('site_id')
                ->nullable()
                ->constrained('sites', 'id', 'warehouses_site_id_FK')
                ->nullOnDelete();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
            $table->unique(['company_id', 'code'], 'warehouses_company_code_un');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
