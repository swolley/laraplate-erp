<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $invoice_line_delivery_note_line_table = ERPTables::InvoiceLineDeliveryNoteLine->value;
        Schema::create($invoice_line_delivery_note_line_table, function (Blueprint $table) use ($invoice_line_delivery_note_line_table): void {
            $table->id();
            $table->foreignId('invoice_line_id')
                ->constrained(ERPTables::InvoiceLines->value, 'id', "{$invoice_line_delivery_note_line_table}_invoice_line_id_FK")
                ->cascadeOnDelete();
            $table->foreignId('delivery_note_line_id')
                ->constrained(ERPTables::DeliveryNoteLines->value, 'id', "{$invoice_line_delivery_note_line_table}_delivery_note_line_id_FK")
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 4)->comment('Qty of this DDT line covered by this invoice line');

            MigrateUtils::timestamps($table);

            $table->unique(
                ['invoice_line_id', 'delivery_note_line_id'],
                "{$invoice_line_delivery_note_line_table}_invoice_dn_UN",
            );
        });

        ERPMigrateUtils::positiveCheck($invoice_line_delivery_note_line_table, 'ildnl_qty_pos_ck', 'quantity');
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::InvoiceLineDeliveryNoteLine->value);
    }
};
