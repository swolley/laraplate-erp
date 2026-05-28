<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\ReturnOrder;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Returns\CustomerReturnReceiptService;

uses(RefreshDatabase::class);

function createReturnOrderFixtures(): array
{
    $company = Company::query()->create([
        'slug' => 'return-order',
        'name' => 'Return Order',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Customer',
        'is_customer' => true,
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'Main',
        'code' => 'MAIN',
    ]);
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Returned item',
        'sku' => 'RET-1',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    return [$company, $party, $warehouse, $item];
}

it('records inbound stock for customer returns', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Approved,
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 3,
        'unit_cost' => '12.5000',
    ]);

    $processed = app(CustomerReturnReceiptService::class)->receive($return_order);

    $level = StockLevel::query()
        ->where('company_id', $company->id)
        ->where('item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();

    expect($processed->status)->toBe(ReturnStatus::Processed)
        ->and($processed->processed_at)->not->toBeNull()
        ->and($level->quantity)->toBe(3)
        ->and((float) $level->weighted_avg_cost)->toBe(12.5);
});

it('prevents processing the same customer return twice', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Approved,
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'unit_cost' => '1.0000',
    ]);

    $service = app(CustomerReturnReceiptService::class);
    $service->receive($return_order);

    expect(fn () => $service->receive($return_order->fresh()))
        ->toThrow(ValidationException::class);
});
