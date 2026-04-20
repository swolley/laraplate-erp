<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Casts\BillingMode;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quotations_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations', 'id', 'quotations_items_quotation_id_FK')->nullable(false)->cascadeOnDelete()->comment('The quotation that the quotation item belongs to');
            $table->foreignId('price_list_item_id')->constrained('price_list_items', 'id', 'quotations_items_price_list_item_id_FK')->nullable(true)->setNullOnDelete()->comment('The price list item that the quotation item belongs to');
            $table->string('name')->comment('The name of the quotation item');
            $table->enum('billing_mode', BillingMode::cases())->nullable(false)->default(BillingMode::UNIT->value)->comment('The billing mode of the quotation item');
            $table->unsignedSmallInteger('quantity')->comment('The quantity of the quotation item')->default(1);
            $table->decimal('unit_price', 15, 4)->nullable()->comment('Unit price in quotation currency context; null if only descriptive line');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations_items');
    }
};
