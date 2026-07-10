<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DiscountType;
use Modules\ERP\Models\Activity;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PartyPriceRule;

uses(RefreshDatabase::class);

it('defines party item and activity relationships', function (): void {
    $rule = new PartyPriceRule;

    expect($rule->party())->toBeInstanceOf(BelongsTo::class)
        ->and($rule->item())->toBeInstanceOf(BelongsTo::class)
        ->and($rule->activity())->toBeInstanceOf(BelongsTo::class)
        ->and($rule->activity()->getRelated())->toBeInstanceOf(Activity::class);
});

it('links party price rules through the party relation', function (): void {
    $company = Company::query()->create([
        'slug' => 'party-rules-rel-' . uniqid(),
        'name' => 'Party Rules Rel Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Customer A',
        'is_customer' => true,
    ]);
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Widget',
        'sku' => 'W-REL',
        'uom' => 'ea',
        'costing_method' => 'weighted_avg',
    ]);

    $rule = PartyPriceRule::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'item_id' => $item->id,
        'priority' => 1,
        'discount_type' => DiscountType::Percent,
        'discount_value' => '10.0000',
    ]);

    expect($party->price_rules())->toBeInstanceOf(HasMany::class)
        ->and($party->price_rules)->toHaveCount(1)
        ->and($party->price_rules->first()?->is($rule))->toBeTrue();
});

it('requires exactly one of item_id or taxonomy_id', function (): void {
    $company = Company::query()->create([
        'slug' => 'price-rule-' . uniqid(),
        'name' => 'Price Rule Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    expect(fn () => PartyPriceRule::query()->create([
        'company_id' => $company->id,
        'discount_type' => DiscountType::Percent,
        'discount_value' => '5.0000',
    ]))->toThrow(ValidationException::class);
});

it('allows taxonomy-only party price rules', function (): void {
    $company = Company::query()->create([
        'slug' => 'price-rule-tax-' . uniqid(),
        'name' => 'Price Rule Tax Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Widget',
        'sku' => 'W-PR',
        'uom' => 'ea',
        'costing_method' => 'weighted_avg',
    ]);

    $rule = PartyPriceRule::query()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'priority' => 1,
        'discount_type' => DiscountType::FixedAmount,
        'discount_value' => '2.0000',
    ]);

    expect($rule->item?->id)->toBe($item->id);
});
