<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DiscountType;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\ERP\Models\Activity;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Entity;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PartyPriceRule;
use Modules\ERP\Models\Pivot\Presettable;
use Modules\ERP\Models\PriceList;
use Modules\ERP\Models\PriceListItem;
use Modules\ERP\Services\Pricing\PriceResolverService;

uses(RefreshDatabase::class);

function createPricingFixture(): array
{
    test()->seed(ERPDatabaseSeeder::class);

    $company = Company::query()->withoutGlobalScopes()->where('is_default', true)->firstOrFail();
    $entity = Entity::query()->withoutGlobalScopes()->where('name', 'activity')->firstOrFail();
    $presettable = Presettable::query()->where('entity_id', $entity->id)->firstOrFail();
    $taxonomy = Activity::query()->forceCreate([
        'parent_id' => null,
        'presettable_id' => $presettable->id,
        'entity_id' => $entity->id,
    ]);
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Widget',
        'sku' => 'W-1',
        'uom' => 'ea',
        'costing_method' => 'weighted_avg',
        'taxonomy_id' => $taxonomy->id,
    ]);
    $price_list = PriceList::query()->create([
        'company_id' => $company->id,
        'name' => 'Default',
        'currency' => 'EUR',
    ]);
    PriceListItem::query()->create([
        'price_list_id' => $price_list->id,
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Widget price',
        'unit_price' => '100.0000',
    ]);

    return [$company, $item, $taxonomy];
}

it('resolves the base unit price from the active price list item', function (): void {
    [$company, $item] = createPricingFixture();

    $result = app(PriceResolverService::class)->resolve((int) $company->id, (int) $item->id);

    expect($result->baseUnitPrice)->toBe('100.0000')
        ->and($result->resolvedUnitPrice)->toBe('100.0000')
        ->and($result->appliedRule)->toBeNull();
});

it('prefers an item-specific list price over its taxonomy price', function (): void {
    [$company, $item] = createPricingFixture();
    $price_list = PriceList::query()->where('company_id', $company->id)->firstOrFail();
    PriceListItem::query()->create([
        'price_list_id' => $price_list->id,
        'item_id' => $item->id,
        'name' => 'Direct widget price',
        'unit_price' => '75.0000',
    ]);

    $result = app(PriceResolverService::class)->resolve((int) $company->id, (int) $item->id);

    expect($result->baseUnitPrice)->toBe('75.0000')
        ->and($result->priceListItem->item_id)->toBe($item->id);
});

it('resolves a direct price for an item without taxonomy', function (): void {
    $company = Company::query()->create([
        'slug' => 'price-direct-' . uniqid(),
        'name' => 'Direct price',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Standalone item',
        'sku' => 'DIRECT-1',
        'uom' => 'ea',
        'costing_method' => 'weighted_avg',
    ]);
    $price_list = PriceList::query()->create([
        'company_id' => $company->id,
        'name' => 'Direct',
        'currency' => 'EUR',
    ]);
    PriceListItem::query()->create([
        'price_list_id' => $price_list->id,
        'item_id' => $item->id,
        'name' => 'Standalone direct price',
        'unit_price' => '33.0000',
    ]);

    $result = app(PriceResolverService::class)->resolve((int) $company->id, (int) $item->id);

    expect($result->resolvedUnitPrice)->toBe('33.0000');
});

it('requires exactly one direct item or taxonomy on a price list item', function (): void {
    [$company, $item, $taxonomy] = createPricingFixture();
    $price_list = PriceList::query()->where('company_id', $company->id)->firstOrFail();

    expect(fn () => PriceListItem::query()->create([
        'price_list_id' => $price_list->id,
        'item_id' => $item->id,
        'taxonomy_id' => $taxonomy->id,
        'name' => 'Ambiguous price',
        'unit_price' => '50.0000',
    ]))->toThrow(ValidationException::class)
        ->and(fn () => PriceListItem::query()->create([
            'price_list_id' => $price_list->id,
            'name' => 'Untargeted price',
            'unit_price' => '50.0000',
        ]))->toThrow(ValidationException::class);
});

it('applies party percent discount rules to the resolved unit price', function (): void {
    [$company, $item, $taxonomy] = createPricingFixture();
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Discounted customer',
        'is_customer' => true,
        'is_supplier' => false,
    ]);
    PartyPriceRule::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'taxonomy_id' => $taxonomy->id,
        'priority' => 1,
        'discount_type' => DiscountType::Percent,
        'discount_value' => '10.0000',
    ]);

    $result = app(PriceResolverService::class)->resolve((int) $company->id, (int) $item->id, (int) $party->id);

    expect($result->resolvedUnitPrice)->toBe('90.0000');
});

it('applies party fixed-amount discounts without going below zero', function (): void {
    [$company, $item, $taxonomy] = createPricingFixture();
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Fixed discount customer',
        'is_customer' => true,
        'is_supplier' => false,
    ]);
    PartyPriceRule::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'taxonomy_id' => $taxonomy->id,
        'priority' => 1,
        'discount_type' => DiscountType::FixedAmount,
        'discount_value' => '15.0000',
    ]);

    $result = app(PriceResolverService::class)->resolve((int) $company->id, (int) $item->id, (int) $party->id);

    expect($result->resolvedUnitPrice)->toBe('85.0000');
});

it('floors fixed discounts that would make the unit price negative', function (): void {
    [$company, $item, $taxonomy] = createPricingFixture();
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Deep discount customer',
        'is_customer' => true,
        'is_supplier' => false,
    ]);
    PartyPriceRule::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'taxonomy_id' => $taxonomy->id,
        'priority' => 1,
        'discount_type' => DiscountType::FixedAmount,
        'discount_value' => '150.0000',
    ]);

    $result = app(PriceResolverService::class)->resolve((int) $company->id, (int) $item->id, (int) $party->id);

    expect($result->resolvedUnitPrice)->toBe('0.0000');
});

it('replaces the list price when a party override rule is configured', function (): void {
    [$company, $item, $taxonomy] = createPricingFixture();
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Contract price customer',
        'is_customer' => true,
        'is_supplier' => false,
    ]);
    PartyPriceRule::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'taxonomy_id' => $taxonomy->id,
        'priority' => 1,
        'discount_type' => DiscountType::OverridePrice,
        'discount_value' => '42.5000',
    ]);

    $result = app(PriceResolverService::class)->resolve((int) $company->id, (int) $item->id, (int) $party->id);

    expect($result->baseUnitPrice)->toBe('100.0000')
        ->and($result->resolvedUnitPrice)->toBe('42.5000');
});

it('rejects items without either a direct price or pricing taxonomy', function (): void {
    $company = Company::query()->create([
        'slug' => 'price-no-tax-' . uniqid(),
        'name' => 'No Taxonomy Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'No taxonomy item',
        'sku' => 'NO-TAX',
        'uom' => 'ea',
        'costing_method' => 'weighted_avg',
    ]);

    expect(fn () => app(PriceResolverService::class)->resolve((int) $company->id, (int) $item->id))
        ->toThrow(ValidationException::class, 'No active direct or taxonomy price list item');
});

it('rejects items when no active price list item matches', function (): void {
    [$company, $item] = createPricingFixture();
    PriceListItem::query()->delete();

    expect(fn () => app(PriceResolverService::class)->resolve((int) $company->id, (int) $item->id))
        ->toThrow(ValidationException::class, 'No active direct or taxonomy price list item');
});
