<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Reporting\StockValuationService;

uses(RefreshDatabase::class);

it('values stock levels and sorts by sku then warehouse code', function (): void {
    $company = Company::query()->create([
        'slug' => 'stock-val-' . uniqid(),
        'name' => 'Stock Valuation Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $item_b = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Item B',
        'sku' => 'SKU-B',
        'uom' => 'ea',
        'costing_method' => 'weighted_avg',
    ]);
    $item_a = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Item A',
        'sku' => 'SKU-A',
        'uom' => 'ea',
        'costing_method' => 'weighted_avg',
    ]);
    $warehouse_z = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'WH Z',
        'code' => 'Z',
    ]);
    $warehouse_a = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'WH A',
        'code' => 'A',
    ]);

    StockLevel::query()->create([
        'company_id' => $company->id,
        'item_id' => $item_b->id,
        'warehouse_id' => $warehouse_z->id,
        'quantity' => '2.0000',
        'weighted_avg_cost' => '5.0000',
    ]);
    StockLevel::query()->create([
        'company_id' => $company->id,
        'item_id' => $item_a->id,
        'warehouse_id' => $warehouse_a->id,
        'quantity' => '3.0000',
        'weighted_avg_cost' => '4.0000',
    ]);

    $result = app(StockValuationService::class)->generate((int) $company->id);

    expect($result['rows'])->toHaveCount(2)
        ->and($result['rows'][0]['sku'])->toBe('SKU-A')
        ->and($result['rows'][0]['warehouse_code'])->toBe('A')
        ->and($result['rows'][0]['value'])->toBe('12.0000')
        ->and($result['rows'][1]['sku'])->toBe('SKU-B')
        ->and($result['rows'][1]['value'])->toBe('10.0000')
        ->and($result['total_quantity'])->toBe('5.0000')
        ->and($result['total_value'])->toBe('22.0000');
});

it('returns empty totals when the company has no stock levels', function (): void {
    $company = Company::query()->create([
        'slug' => 'stock-empty-' . uniqid(),
        'name' => 'Empty Stock Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $result = app(StockValuationService::class)->generate((int) $company->id);

    expect($result['rows'])->toBeEmpty()
        ->and($result['total_quantity'])->toBe('0.0000')
        ->and($result['total_value'])->toBe('0.0000');
});
