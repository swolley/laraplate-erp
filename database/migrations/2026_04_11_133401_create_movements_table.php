<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;
use Modules\ERP\Casts\MovementType;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $movements_table = ERPTables::Movements->value;
        Schema::create($movements_table, function (Blueprint $table) use ($movements_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->enum('type', MovementType::values())->index("{$movements_table}_type_IDX");
            $table->date('occurred_on')->index("{$movements_table}_occurred_on_IDX");
            $table->decimal('amount_doc', 15, 4);
            $table->char('currency_doc', 3);
            $table->decimal('amount_local', 15, 4)->nullable();
            $table->char('currency_local', 3)->nullable();
            $table->decimal('fx_rate', 18, 8)->nullable();
            $table->foreignId('counterparty_account_id')
                ->constrained(ERPTables::Accounts->value, 'id', "{$movements_table}_counterparty_account_id_FK")
                ->restrictOnDelete();
            $table->foreignId('posted_journal_entry_id')
                ->nullable()
                ->unique()
                ->constrained(ERPTables::JournalEntries->value, 'id', "{$movements_table}_posted_journal_entry_id_FK")
                ->restrictOnDelete();
            $table->text('description')->nullable();

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: true);
        });

        ERPMigrateUtils::positiveCheck($movements_table, 'movement_amount_doc_pos_ck', 'amount_doc');
        ERPMigrateUtils::nullableNonNegativeCheck($movements_table, 'movement_amount_local_nn_ck', 'amount_local');
        ERPMigrateUtils::nullableNonNegativeCheck($movements_table, 'movement_fx_rate_nn_ck', 'fx_rate');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ERPTables::Movements->value);
    }
};
