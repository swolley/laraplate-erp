<?php

declare(strict_types=1);

/**
 * Party, Item and Warehouse are company-scoped, and the failure mode that matters is silent: a
 * factory that invents its own company scopes the test away from the data it just created, and
 * every assertion still passes for the wrong reason. These tests count companies for that reason.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\Warehouse;

uses(RefreshDatabase::class);

it('creates a party, an item and a warehouse', function (): void {
    expect(Party::factory()->create()->exists)->toBeTrue()
        ->and(Item::factory()->create()->exists)->toBeTrue()
        ->and(Warehouse::factory()->create()->exists)->toBeTrue();
});

it('reuses the company it was given instead of creating another', function (): void {
    $company = Company::factory()->create();

    $party = Party::factory()->for($company)->create();
    $item = Item::factory()->for($company)->create();
    $warehouse = Warehouse::factory()->for($company)->create();

    expect(Company::query()->count())->toBe(1)
        ->and($party->company_id)->toBe($company->id)
        ->and($item->company_id)->toBe($company->id)
        ->and($warehouse->company_id)->toBe($company->id);
});

it('separates customers from suppliers', function (): void {
    $customer = Party::factory()->customer()->create();
    $supplier = Party::factory()->supplier()->create();

    expect($customer->is_customer)->toBeTrue()
        ->and($customer->is_supplier)->toBeFalse()
        ->and($supplier->is_supplier)->toBeTrue()
        ->and($supplier->is_customer)->toBeFalse();
});

it('keeps item sku unique inside one company', function (): void {
    $company = Company::factory()->create();

    $skus = Item::factory()->for($company)->count(3)->create()->pluck('sku');

    expect($skus->unique())->toHaveCount(3);
});
