<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained('invoices', 'id', 'invoice_lines_invoice_id_FK')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->text('description')->nullable();
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('unit_price', 15, 4);
            $table->foreignId('tax_code_id')
                ->nullable()
                ->constrained('tax_codes', 'id', 'invoice_lines_tax_code_id_FK')
                ->nullOnDelete();
            $table->string('tax_code', 64)->nullable()->comment('Snapshot: TaxCode.code at posting');
            $table->decimal('tax_rate', 8, 4)->nullable()->comment('Snapshot: percentage frozen at posting');
            $table->string('tax_label')->nullable()->comment('Snapshot: human label at posting');

            $table->unique(['invoice_id', 'line_no'], 'invoice_lines_invoice_line_UN');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
