<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Enums\CoreTables;
use Modules\ERP\Casts\DiscountType;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Entity;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PartyPriceRule;
use Modules\ERP\Models\Pivot\Presettable;
use Modules\ERP\Models\PriceList;
use Modules\ERP\Services\Pricing\PriceResolverService;

uses(RefreshDatabase::class);

function insertPricingActivityTaxonomy(string $slug): int
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
        'name' => 'Consulting',
        'slug' => Str::slug($slug),
        'components' => json_encode([], JSON_THROW_ON_ERROR),
        'created_at' => $now,
        'updated_at' => $now,
        'deleted_at' => null,
    ]);

    return $taxonomy_id;
}

it('resolves active taxonomy price list item as base price', function (): void {
    $taxonomy_id = insertPricingActivityTaxonomy('pricing-base');
    $company = Company::query()->where('slug', 'default')->firstOrFail();
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Consulting',
        'sku' => 'CONS-1',
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
        'name' => 'Consulting',
        'uom' => 'h',
        'unit_price' => '100.0000',
        'valid_from' => now()->subDay(),
    ]);

    $result = app(PriceResolverService::class)->resolve($company->id, $item->id);

    expect($result->baseUnitPrice)->toBe('100.0000')
        ->and($result->resolvedUnitPrice)->toBe('100.0000')
        ->and($result->appliedRule)->toBeNull();
});

it('applies party item rule before taxonomy rule', function (): void {
    $taxonomy_id = insertPricingActivityTaxonomy('pricing-rules');
    $company = Company::query()->where('slug', 'default')->firstOrFail();
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Customer',
        'is_customer' => true,
    ]);
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Consulting',
        'sku' => 'CONS-2',
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
        'name' => 'Consulting',
        'unit_price' => '100.0000',
        'valid_from' => now()->subDay(),
    ]);
    PartyPriceRule::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'taxonomy_id' => $taxonomy_id,
        'priority' => 10,
        'discount_type' => DiscountType::Percent,
        'discount_value' => '10.0000',
        'valid_from' => now()->subDay(),
    ]);
    PartyPriceRule::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'item_id' => $item->id,
        'priority' => 20,
        'discount_type' => DiscountType::OverridePrice,
        'discount_value' => '80.0000',
        'valid_from' => now()->subDay(),
    ]);

    $result = app(PriceResolverService::class)->resolve($company->id, $item->id, $party->id);

    expect($result->resolvedUnitPrice)->toBe('80.0000')
        ->and($result->appliedRule?->item_id)->toBe($item->id);
});

it('rejects rules that target both item and taxonomy', function (): void {
    $taxonomy_id = insertPricingActivityTaxonomy('pricing-invalid');
    $company = Company::query()->where('slug', 'default')->firstOrFail();
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Consulting',
        'sku' => 'CONS-3',
        'uom' => 'h',
        'costing_method' => 'weighted_avg',
        'taxonomy_id' => $taxonomy_id,
    ]);

    expect(fn () => PartyPriceRule::query()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'taxonomy_id' => $taxonomy_id,
        'discount_type' => DiscountType::Percent,
        'discount_value' => '5.0000',
    ]))->toThrow(ValidationException::class);
});
