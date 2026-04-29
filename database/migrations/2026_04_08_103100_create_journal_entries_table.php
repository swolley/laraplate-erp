<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Helpers\ERPMigrateUtils;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('fiscal_period_id')
                ->nullable()
                ->constrained('fiscal_periods', 'id', 'journal_entries_fiscal_period_id_FK')
                ->restrictOnDelete();
            $table->timestamp('posted_at')->nullable()->comment('Null until posted; immutable after set');
            $table->unsignedBigInteger('posted_by')->nullable()->index()->comment('users.id when posted; no FK for module isolation');
            $table->nullableMorphs('reference', 'journal_entries_reference_idx');
            $table->text('description')->nullable();

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
