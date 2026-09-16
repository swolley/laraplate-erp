<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $invoices_table = ERPTables::Invoices->value;
        Schema::create($invoices_table, function (Blueprint $table) use ($invoices_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('party_id')
                ->nullable()
                ->constrained(ERPTables::Parties->value, 'id', "{$invoices_table}_party_id_FK")
                ->restrictOnDelete();
            $table->enum('direction', array_map(
                static fn (InvoiceDirection $d): string => $d->value,
                InvoiceDirection::cases(),
            ));
            $table->string('invoice_type', 32)->default('invoice');
            $table->foreignId('credited_invoice_id')->nullable()
                ->constrained(ERPTables::Invoices->value, 'id', "{$invoices_table}_credited_invoice_FK")
                ->restrictOnDelete();
            $table->string('reference', 64)->nullable()->comment('Assigned by DocumentNumberAllocator at posting time');
            $table->char('currency', 3);
            $table->timestamp('posted_at')->nullable()->index();
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->constrained(ERPTables::JournalEntries->value, 'id', "{$invoices_table}_journal_entry_id_FK")
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('einvoice_transmission_format', 5)->nullable()->comment('FatturaPA transmission format, e.g. FPR12 or FPA12');
            $table->string('einvoice_recipient_code', 7)->nullable();
            $table->string('einvoice_pec_email')->nullable();

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::Invoices->value);
    }
};
