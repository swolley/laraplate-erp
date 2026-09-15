<?php

declare(strict_types=1);

/**
 * A dev seeder is run repeatedly, on databases that are rarely empty: after a migration, after a
 * colleague's branch, twice by accident. So the assertion that matters is not "it creates data"
 * but "running it again changes nothing" — a seeder that duplicates its own dataset is worse than
 * one that seeds nothing, because the second run is the one nobody checks.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Database\Seeders\DevERPDatabaseSeeder;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\GoodsReceipt;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\Warehouse;

uses(RefreshDatabase::class);

/**
 * The module seeders take a DatabaseManager, so they are resolved through the test helper rather
 * than instantiated by hand.
 */
function seedErpDev(object $test): void
{
    $test->seed(ERPDatabaseSeeder::class);
    $test->seed(DevERPDatabaseSeeder::class);
}

/**
 * @return array<string, int>
 */
function erpDemoCounts(): array
{
    return [
        'companies' => Company::query()->count(),
        'parties' => Party::query()->count(),
        'items' => Item::query()->count(),
        'warehouses' => Warehouse::query()->count(),
        'sales_orders' => SalesOrder::query()->count(),
        'delivery_notes' => DeliveryNote::query()->count(),
        'purchase_orders' => PurchaseOrder::query()->count(),
        'goods_receipts' => GoodsReceipt::query()->count(),
        'invoices' => Invoice::query()->count(),
    ];
}

it('seeds a demo company with both commercial flows', function (): void {
    seedErpDev($this);

    $company = Company::query()->where('slug', DevERPDatabaseSeeder::DEMO_COMPANY_SLUG)->first();

    expect($company)->not->toBeNull()
        ->and(Party::query()->where('company_id', $company->id)->where('is_customer', true)->count())->toBe(1)
        ->and(Party::query()->where('company_id', $company->id)->where('is_supplier', true)->count())->toBe(1)
        ->and(Warehouse::query()->where('company_id', $company->id)->count())->toBe(1)
        ->and(Item::query()->where('company_id', $company->id)->count())->toBe(3);

    $sales_order = SalesOrder::query()->where('company_id', $company->id)->first();
    $purchase_order = PurchaseOrder::query()->where('company_id', $company->id)->first();

    expect($sales_order)->not->toBeNull()
        ->and($sales_order->lines()->count())->toBeGreaterThan(0)
        ->and($purchase_order)->not->toBeNull()
        ->and($purchase_order->lines()->count())->toBeGreaterThan(0);

    expect(DeliveryNote::query()->where('company_id', $company->id)->count())->toBe(1)
        ->and(GoodsReceipt::query()->where('company_id', $company->id)->count())->toBe(1)
        ->and(Invoice::query()->where('company_id', $company->id)->count())->toBe(2);
});

it('changes nothing when it runs a second time', function (): void {
    seedErpDev($this);
    $first = erpDemoCounts();

    $this->seed(DevERPDatabaseSeeder::class);

    expect(erpDemoCounts())->toBe($first);
});

it('uses stable identifiers a test or a screen can anchor on', function (): void {
    seedErpDev($this);

    expect(Item::query()->where('sku', 'DEMO-ITEM-001')->exists())->toBeTrue()
        ->and(Warehouse::query()->where('code', 'DEMO-WH')->exists())->toBeTrue();
});

it('keeps seeding the taxonomies it seeded before', function (): void {
    seedErpDev($this);

    expect(Modules\ERP\Models\OpportunityStage::query()->count())->toBeGreaterThan(0)
        ->and(Modules\ERP\Models\Activity::query()->count())->toBeGreaterThan(0);
});
