<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\PaymentDirection;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('party_id')
                ->constrained('parties', 'id', 'payments_party_id_FK')
                ->restrictOnDelete();
            $table->enum('direction', array_map(
                static fn (PaymentDirection $d): string => $d->value,
                PaymentDirection::cases(),
            ));
            $table->date('payment_date');
            ERPMigrateUtils::moneyColumns($table);
            $table->string('reference', 64)->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->constrained('journal_entries', 'id', 'payments_journal_entry_id_FK')
                ->restrictOnDelete();
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
        Schema::dropIfExists('payments');
    }
};
