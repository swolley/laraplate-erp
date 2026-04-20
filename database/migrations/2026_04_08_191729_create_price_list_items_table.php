<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('price_list_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists', 'id', 'price_list_items_price_list_id_FK')->cascadeOnDelete();
            $table->foreignId('taxonomy_id')->constrained('taxonomies', 'id', 'price_list_items_taxonomy_id_FK')->restrictOnDelete();
            $table->string('name');
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
        Schema::dropIfExists('price_list_items');
    }
};
