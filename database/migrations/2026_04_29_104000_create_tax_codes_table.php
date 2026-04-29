<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\TaxKind;
use Modules\ERP\Helpers\ERPMigrateUtils;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_codes', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->string('code', 64)->comment('Immutable business key (e.g. IT_VAT_22); supersession uses a new code');
            $table->enum('kind', array_map(
                static fn (TaxKind $k): string => $k->value,
                TaxKind::cases(),
            ));
            $table->char('country', 2)->comment('ISO 3166-1 alpha-2 for jurisdiction');
            $table->decimal('rate', 8, 4)->comment('Percentage rate (e.g. 22.0000 for 22% VAT)');
            $table->string('label')->comment('Frozen display label at seed/create time');
            $table->boolean('is_active')->default(true)->index();
            $table->date('effective_from')->comment('First calendar day this row may apply');
            $table->foreignId('replaced_by_tax_code_id')
                ->nullable()
                ->constrained('tax_codes', 'id', 'tax_codes_replaced_by_id_FK')
                ->nullOnDelete();
            $table->json('meta')->nullable()->comment('Extensions e.g. SDI regime codes');

            $table->unique(['company_id', 'code'], 'tax_codes_company_code_UN');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_codes');
    }
};
