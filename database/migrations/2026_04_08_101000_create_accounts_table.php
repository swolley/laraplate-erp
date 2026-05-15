<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $accounts_table = ERPTables::Accounts->value;
        Schema::create($accounts_table, function (Blueprint $table) use ($accounts_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->string('code', 32)->comment('Immutable logical code unique per company (Italian PGC-style numerics)');
            $table->string('name')->comment('Display name');
            $table->enum('kind', array_map(static fn (AccountKind $k): string => $k->value, AccountKind::cases()))
                ->comment('Balance-sheet / P&L classification');
            $table->foreignId('parent_id')->nullable()->constrained($accounts_table, 'id', "{$accounts_table}_parent_id_FK")->restrictOnDelete();
            $table->json('meta')->nullable()->comment('Extensions e.g. civilistico_code, oic_mapping');
            $table->boolean('is_active')->default(true)->index("{$accounts_table}_is_active_IDX");

            $table->unique(['company_id', 'code'], "{$accounts_table}_company_code_UN");
            $table->index(['company_id', 'kind'], "{$accounts_table}_company_kind_idx");

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::Accounts->value);
    }
};
