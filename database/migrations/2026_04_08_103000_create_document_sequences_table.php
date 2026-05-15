<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $document_sequences_table = ERPTables::DocumentSequences->value;
        Schema::create($document_sequences_table, function (Blueprint $table) use ($document_sequences_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->enum('document_type', array_map(
                static fn (DocumentType $t): string => $t->value,
                DocumentType::cases(),
            ))->comment('Which document stream this row advances');
            $table->unsignedSmallInteger('fiscal_year')
                ->default(0)
                ->comment('Calendar/fiscal year bucket; 0 = not reset by fiscal year (e.g. operational quotes)');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->boolean('gap_allowed')->default(false)->comment('When true, holes in the numeric sequence may exist by policy');
            $table->string('prefix', 32)->default('')->comment('Prepended to the formatted number');
            $table->unsignedTinyInteger('padding')->default(5)->comment('Zero-padding width for the numeric segment');

            $table->unique(
                ['company_id', 'document_type', 'fiscal_year'],
                "{$document_sequences_table}_company_type_fy_UN",
            );

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::DocumentSequences->value);
    }
};
