<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Enums\CoreTables;
use Modules\ERP\Casts\DiscountType;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Entity;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PartyPriceRule;
use Modules\ERP\Models\Pivot\Presettable;
use Modules\ERP\Models\PriceList;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Services\Pricing\InvoiceLinePricingService;

uses(RefreshDatabase::class);

function insertInvoicePricingActivityTaxonomy(string $slug): int
{
    Artisan::call('db:seed', ['--class' => ERPDatabaseSeeder::class, '--no-interaction' => true]);

    $entity = Entity::query()->withoutGlobalScopes()->where('name', 'activity')->firstOrFail();
    $presettable = Presettable::query()->withoutGlobalScopes()
        ->where('entity_id', $entity->id)
        ->whereNull('deleted_at')
        ->latest('id')
        ->firstOrFail();
    $now = now();

    $taxonomy_id = DB::table(CoreTables::Taxonomies->value)->insertGetId([
        'entity_id' => $entity->id,
        'presettable_id' => $presettable->id,
        'shared_components' => null,
        'parent_id' => null,
        'logo' => null,
        'logo_full' => null,
        'is_active' => 1,
        'order_column' => 0,
        'created_at' => $now,
        'updated_at' => $now,
        'deleted_at' => null,
        'locked_at' => null,
        'locked_user_id' => null,
        'valid_from' => $now,
        'valid_to' => null,
    ]);

    DB::table(CoreTables::TaxonomiesTranslations->value)->insert([
        'taxonomy_id' => $taxonomy_id,
        'locale' => 'en',
        'name' => 'Implementation',
        'slug' => Str::slug($slug),
        'components' => json_encode([], JSON_THROW_ON_ERROR),
        'created_at' => $now,
        'updated_at' => $now,
        'deleted_at' => null,
    ]);

    return $taxonomy_id;
}

it('builds invoice line defaults from a sales order line and current party pricing', function (): void {
    $taxonomy_id = insertInvoicePricingActivityTaxonomy('invoice-pricing');
    $company = Company::query()->where('slug', 'default')->firstOrFail();
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Customer',
        'is_customer' => true,
    ]);
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Implementation',
        'sku' => 'IMPL-1',
        'uom' => 'h',
        'costing_method' => 'weighted_avg',
        'taxonomy_id' => $taxonomy_id,
    ]);
    $price_list = PriceList::query()->create([
        'company_id' => $company->id,
        'name' => 'Standard EUR',
        'currency' => 'EUR',
        'valid_from' => now()->subDay(),
    ]);
    $price_list->price_list_items()->create([
        'taxonomy_id' => $taxonomy_id,
        'name' => 'Implementation',
        'unit_price' => '100.0000',
        'valid_from' => now()->subDay(),
    ]);
    PartyPriceRule::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'item_id' => $item->id,
        'discount_type' => DiscountType::Percent,
        'discount_value' => '15.0000',
        'valid_from' => now()->subDay(),
    ]);
    $sales_order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::Confirmed,
    ]);
    $sales_order_line = SalesOrderLine::query()->create([
        'sales_order_id' => $sales_order->id,
        'item_id' => $item->id,
        'name' => 'Implementation work',
        'qty_ordered' => 5,
        'qty_delivered' => 0,
        'qty_invoiced' => 2,
        'unit_price' => '95.0000',
        'status' => SalesOrderLineStatus::PartiallyEvased,
    ]);

    $defaults = app(InvoiceLinePricingService::class)->defaultsFromSalesOrderLine(
        company_id: $company->id,
        sales_order_line_id: $sales_order_line->id,
        party_id: $party->id,
        currency: 'EUR',
    );

    expect($defaults)->toMatchArray([
        'description' => 'Implementation work',
        'quantity' => '3.0000',
        'unit_price' => '85.0000',
    ]);
});
