<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Helpers\BusinessMigrateUtils;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')
                ->constrained('journal_entries', 'id', 'journal_entry_lines_entry_id_FK')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no')->comment('Stable ordering within the entry');
            $table->foreignId('account_id')
                ->constrained('accounts', 'id', 'journal_entry_lines_account_id_FK')
                ->restrictOnDelete();

            BusinessMigrateUtils::moneyColumns($table);

            $table->string('tax_code', 32)->nullable();
            $table->decimal('tax_rate', 8, 4)->nullable();
            $table->string('tax_label')->nullable();
            $table->text('description')->nullable();

            $table->unique(['journal_entry_id', 'line_no'], 'journal_entry_lines_entry_line_UN');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
