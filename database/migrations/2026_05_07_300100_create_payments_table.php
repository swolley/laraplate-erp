<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\PaymentDirection;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $payments_table = ERPTables::Payments->value;
        Schema::create($payments_table, function (Blueprint $table) use ($payments_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('party_id')
                ->constrained(ERPTables::Parties->value, 'id', "{$payments_table}_party_id_FK")
                ->restrictOnDelete();
            MigrateUtils::prefixIndex($table, 'party_id');
            $table->enum('direction', array_map(
                static fn (PaymentDirection $d): string => $d->value,
                PaymentDirection::cases(),
            ));
            $table->date('payment_date');
            ERPMigrateUtils::moneyColumns($table);
            $table->string('reference', 64)->nullable();
            $table->foreignId('bank_account_id')
                ->nullable()
                ->constrained(ERPTables::BankAccounts->value, 'id', "{$payments_table}_bank_account_id_FK")
                ->nullOnDelete();
            MigrateUtils::prefixIndex($table, 'bank_account_id');
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->constrained(ERPTables::JournalEntries->value, 'id', "{$payments_table}_journal_entry_id_FK")
                ->restrictOnDelete();
            MigrateUtils::prefixIndex($table, 'journal_entry_id');
            $table->text('notes')->nullable();

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::Payments->value);
    }
};
