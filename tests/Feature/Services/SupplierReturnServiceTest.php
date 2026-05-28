<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\SupplierReturn;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Inventory\StockMovementService;
use Modules\ERP\Services\Returns\SupplierReturnShipmentService;

uses(RefreshDatabase::class);

function createSupplierReturnFixtures(): array
{
    $company = Company::query()->create([
        'slug' => 'supplier-return',
        'name' => 'Supplier Return',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Supplier',
        'is_supplier' => true,
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'Main',
        'code' => 'MAIN',
    ]);
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Supplier item',
        'sku' => 'SUP-1',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    app(StockMovementService::class)->recordInbound($company->id, $item->id, $warehouse->id, 5, '10.0000');

    return [$company, $party, $warehouse, $item];
}

it('records outbound stock for supplier returns', function (): void {
    [$company, $party, $warehouse, $item] = createSupplierReturnFixtures();

    $supplier_return = SupplierReturn::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Approved,
    ]);
    $supplier_return->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 2,
    ]);

    $processed = app(SupplierReturnShipmentService::class)->ship($supplier_return);

    $level = StockLevel::query()
        ->where('company_id', $company->id)
        ->where('item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();

    expect($processed->status)->toBe(ReturnStatus::Processed)
        ->and($processed->processed_at)->not->toBeNull()
        ->and($level->quantity)->toBe(3);
});
