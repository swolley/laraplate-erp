<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Enums\ERPTables;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $price_list_items_table = ERPTables::PriceListItems->value;
        Schema::create($price_list_items_table, function (Blueprint $table) use ($price_list_items_table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained(ERPTables::PriceLists->value, 'id', "{$price_list_items_table}_price_list_id_FK")->cascadeOnDelete();
            $table->foreignId('taxonomy_id')->constrained(CoreTables::Taxonomies->value, 'id', "{$price_list_items_table}_taxonomy_id_FK")->restrictOnDelete();
            $table->string('name')->comment('The name of the price list item');
            $table->string('uom')->nullable()->comment('Unit of measure label, e.g. h, day');
            $table->decimal('unit_price', 15, 4)->default(0)->comment('Amount in price_lists.currency');

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
        Schema::dropIfExists(ERPTables::PriceListItems->value);
    }
};
