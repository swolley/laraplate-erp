<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\BillingMode;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $quotation_items_table = ERPTables::QuotationItems->value;
        Schema::create($quotation_items_table, function (Blueprint $table) use ($quotation_items_table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained(ERPTables::Quotations->value, 'id', "{$quotation_items_table}_quotation_id_FK")->nullable(false)->cascadeOnDelete()->comment('The quotation that the quotation item belongs to');
            $table->foreignId('price_list_item_id')->nullable()->constrained(ERPTables::PriceListItems->value, 'id', "{$quotation_items_table}_price_list_item_id_FK")->nullOnDelete()->comment('The price list item that the quotation item belongs to');
            $table->string('name')->comment('The name of the quotation item');
            $table->enum('billing_mode', BillingMode::cases())->nullable(false)->default(BillingMode::Unit->value)->index("{$quotation_items_table}_billing_mode_idx")->comment('The billing mode of the quotation item');
            $table->decimal('quantity', 15, 4)->comment('The quantity of the quotation item')->default(1);
            $table->decimal('unit_price', 15, 4)->nullable()->comment('Unit price in quotation currency context; null if only descriptive line');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });

        ERPMigrateUtils::positiveCheck($quotation_items_table, 'qi_qty_pos_ck', 'quantity');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ERPTables::QuotationItems->value);
    }
};
