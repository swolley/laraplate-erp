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
        $invoice_lines_table = ERPTables::InvoiceLines->value;
        Schema::create($invoice_lines_table, function (Blueprint $table) use ($invoice_lines_table): void {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained(ERPTables::Invoices->value, 'id', "{$invoice_lines_table}_invoice_id_FK")
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->text('description')->nullable();
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('qty_returned', 15, 4)->default(0);
            $table->decimal('unit_price', 15, 4);
            $table->foreignId('tax_code_id')
                ->nullable()
                ->constrained(ERPTables::TaxCodes->value, 'id', "{$invoice_lines_table}_tax_code_id_FK")
                ->nullOnDelete();
            $table->string('tax_code', 64)->nullable()->comment('Snapshot: TaxCode.code at posting');
            $table->decimal('tax_rate', 8, 4)->nullable()->comment('Snapshot: percentage frozen at posting');
            $table->string('tax_label')->nullable()->comment('Snapshot: human label at posting');

            $table->foreignId('purchase_order_line_id')
                ->nullable()
                ->constrained(ERPTables::PurchaseOrderLines->value, 'id', "{$invoice_lines_table}_po_line_id_FK")
                ->nullOnDelete();
            $table->foreignId('goods_receipt_line_id')
                ->nullable()
                ->constrained(ERPTables::GoodsReceiptLines->value, 'id', "{$invoice_lines_table}_gr_line_id_FK")
                ->nullOnDelete();
            $table->string('match_status', 20)->nullable()->comment('Three-way match result');
            $table->json('match_discrepancy')->nullable()->comment('Details of price/qty discrepancies');

            $table->unique(['invoice_id', 'line_no'], "{$invoice_lines_table}_invoice_line_UN");

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });

        ERPMigrateUtils::positiveCheck($invoice_lines_table, 'il_qty_pos_ck', 'quantity');
        ERPMigrateUtils::nonNegativeCheck($invoice_lines_table, 'il_qret_nn_ck', 'qty_returned');
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::InvoiceLines->value);
    }
};
