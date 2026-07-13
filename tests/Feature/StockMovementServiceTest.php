<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\StockCostLayer;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Inventory\StockMovementService;

uses(RefreshDatabase::class);

function assert_decimal_close(string|float $expected, string|float $actual, float $epsilon = 0.0001): void
{
    expect(abs((float) $expected - (float) $actual))->toBeLessThan($epsilon);
}

it('applies weighted average costing on inbound and outbound', function (): void {
    $company = Company::query()->create([
        'slug' => 'inv-wavg',
        'name' => 'Inv Wavg',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'Main',
        'code' => 'MAIN',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Bolt',
        'sku' => 'B-1',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    $service = app(StockMovementService::class);

    $service->recordInbound($company->id, $item->id, $warehouse->id, 10, '5.0000');
    $service->recordInbound($company->id, $item->id, $warehouse->id, 10, '15.0000');

    $level = StockLevel::query()
        ->where('company_id', $company->id)
        ->where('item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();

    expect((string) $level->quantity)->toBe('20.0000');
    assert_decimal_close('10.0000', (string) $level->weighted_avg_cost);

    expect(StockCostLayer::query()->count())->toBe(0);

    $out = $service->recordOutbound($company->id, $item->id, $warehouse->id, 5);

    assert_decimal_close('10.0000', (string) $out->unit_cost);

    $level->refresh();

    expect((string) $level->quantity)->toBe('15.0000');
    assert_decimal_close('10.0000', (string) $level->weighted_avg_cost);
});

it('consumes fifo layers oldest first and sets movement unit cost', function (): void {
    $company = Company::query()->create([
        'slug' => 'inv-fifo',
        'name' => 'Inv Fifo',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'Hub',
        'code' => 'HUB',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Gear',
        'sku' => 'G-9',
        'uom' => 'pcs',
        'costing_method' => 'fifo',
    ]);

    $service = app(StockMovementService::class);

    $service->recordInbound($company->id, $item->id, $warehouse->id, 10, '5.0000');
    $service->recordInbound($company->id, $item->id, $warehouse->id, 10, '15.0000');

    $out = $service->recordOutbound($company->id, $item->id, $warehouse->id, 12);

    $expected_unit = (10 * 5 + 2 * 15) / 12;

    assert_decimal_close($expected_unit, (float) $out->unit_cost, 0.001);

    $layers = StockCostLayer::query()
        ->where('item_id', $item->id)
        ->orderBy('id')
        ->get();

    expect($layers)->toHaveCount(2)
        ->and((string) $layers[0]->qty_remaining)->toBe('0.0000')
        ->and((string) $layers[1]->qty_remaining)->toBe('8.0000');
});

it('consumes only required fifo layers while preserving costing', function (): void {
    $company = Company::query()->create([
        'slug' => 'inv-fifo-many',
        'name' => 'Inv Fifo Many',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'FIFO Hub',
        'code' => 'FIFO-HUB',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Layered Item',
        'sku' => 'LAYER-1',
        'uom' => 'pcs',
        'costing_method' => 'fifo',
    ]);

    $service = app(StockMovementService::class);

    for ($i = 1; $i <= 30; $i++) {
        $service->recordInbound($company->id, $item->id, $warehouse->id, 1, (string) $i);
    }

    $out = $service->recordOutbound($company->id, $item->id, $warehouse->id, 3);

    assert_decimal_close('2.0000', (string) $out->unit_cost);

    $remaining = StockCostLayer::query()
        ->where('company_id', $company->id)
        ->where('item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->orderBy('id')
        ->pluck('qty_remaining')
        ->map(static fn (mixed $value): string => (string) $value)
        ->all();

    expect(array_slice($remaining, 0, 4))->toBe(['0.0000', '0.0000', '0.0000', '1.0000']);
});

it('preserves fractional stock quantities', function (): void {
    $company = Company::query()->create([
        'slug' => 'inv-decimal',
        'name' => 'Inv Decimal',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'Decimal Hub',
        'code' => 'D-HUB',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Cable',
        'sku' => 'C-1',
        'uom' => 'm',
        'costing_method' => 'weighted_avg',
    ]);

    $service = app(StockMovementService::class);

    $inbound = $service->recordInbound($company->id, $item->id, $warehouse->id, '1.5000', '4.0000');
    $outbound = $service->recordOutbound($company->id, $item->id, $warehouse->id, '0.2500');

    $level = StockLevel::query()
        ->where('company_id', $company->id)
        ->where('item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();

    expect((string) $inbound->quantity)->toBe('1.5000')
        ->and((string) $outbound->quantity)->toBe('0.2500')
        ->and((string) $level->quantity)->toBe('1.2500');
});

it('rejects outbound when quantity exceeds on hand stock', function (): void {
    $company = Company::query()->create([
        'slug' => 'inv-short',
        'name' => 'Inv Short',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'W1',
        'code' => 'W1',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Nut',
        'sku' => 'N-1',
        'uom' => 'pcs',
        'costing_method' => 'fifo',
    ]);

    $service = app(StockMovementService::class);

    $service->recordInbound($company->id, $item->id, $warehouse->id, 3, '1.0000');

    $service->recordOutbound($company->id, $item->id, $warehouse->id, 3);

    expect(fn () => $service->recordOutbound($company->id, $item->id, $warehouse->id, 1))
        ->toThrow(ValidationException::class);
});

it('persists optional polymorphic source on movements', function (): void {
    $company = Company::query()->create([
        'slug' => 'inv-morph',
        'name' => 'Inv Morph',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'W',
        'code' => 'W',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Washer',
        'sku' => 'W-2',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Acme',
    ]);

    $service = app(StockMovementService::class);

    $movement = $service->recordInbound(
        $company->id,
        $item->id,
        $warehouse->id,
        4,
        '2.5000',
        $party,
    );

    $movement->refresh();

    expect($movement->source_type)->toBe(Party::class)
        ->and((int) $movement->source_id)->toBe((int) $party->id);
});
