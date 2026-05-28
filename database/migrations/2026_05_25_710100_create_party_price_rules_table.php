<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\ERP\Casts\DiscountType;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $party_price_rules_table = ERPTables::PartyPriceRules->value;

        Schema::create($party_price_rules_table, function (Blueprint $table) use ($party_price_rules_table): void {
            $table->id();
            ERPMigrateUtils::companyForeign($table);
            $table->foreignId('party_id')->nullable()->constrained(ERPTables::Parties->value, 'id', "{$party_price_rules_table}_party_id_FK")->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained(ERPTables::Items->value, 'id', "{$party_price_rules_table}_item_id_FK")->cascadeOnDelete();
            $table->foreignId('taxonomy_id')->nullable()->constrained(CoreTables::Taxonomies->value, 'id', "{$party_price_rules_table}_taxonomy_id_FK")->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->enum('discount_type', array_map(static fn (DiscountType $type): string => $type->value, DiscountType::cases()));
            $table->decimal('discount_value', 15, 4)->default(0);

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
                hasValidity: true,
                isValidityRequired: false,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(ERPTables::PartyPriceRules->value);
    }
};
