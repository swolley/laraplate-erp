<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Customer;
use Modules\ERP\Models\GoodsReceipt;
use Modules\ERP\Models\GoodsReceiptLine;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\Warehouse;

uses(RefreshDatabase::class);

it('posts inbound stock and updates purchase order lines when posted_at is set', function (): void {
    $company = Company::query()->create([
        'slug' => 'gr-inv',
        'name' => 'Gr Inv',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'WH',
        'code' => 'GR-WH',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Bolt',
        'sku' => 'B-GR',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    $supplier = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'Supplier',
    ]);

    $po = PurchaseOrder::query()->create([
        'company_id' => $company->id,
        'customer_id' => $supplier->id,
        'currency' => 'EUR',
        'status' => 'confirmed',
    ]);

    $po_line = PurchaseOrderLine::query()->create([
        'purchase_order_id' => $po->id,
        'item_id' => $item->id,
        'name' => 'Bolt',
        'qty_ordered' => 50,
        'qty_received' => 0,
    ]);

    $receipt = GoodsReceipt::query()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $po->id,
        'reference' => 'GR-1',
    ]);

    GoodsReceiptLine::query()->create([
        'company_id' => $company->id,
        'goods_receipt_id' => $receipt->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 12,
        'unit_cost' => '3.5000',
        'purchase_order_line_id' => $po_line->id,
    ]);

    $receipt->update(['posted_at' => now()]);

    $po_line->refresh();
    $level = StockLevel::query()
        ->where('company_id', $company->id)
        ->where('item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();

    expect($po_line->qty_received)->toBe(12)
        ->and($level->quantity)->toBe(12)
        ->and($receipt->fresh()->inventory_posted_at)->not->toBeNull()
        ->and($receipt->fresh()->posted_at)->not->toBeNull();
});

it('rejects receipt quantity above remaining on the purchase order line', function (): void {
    $company = Company::query()->create([
        'slug' => 'gr-over',
        'name' => 'Gr Over',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'W',
        'code' => 'GR-O',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Nut',
        'sku' => 'N-GR',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    $supplier = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'S',
    ]);

    $po = PurchaseOrder::query()->create([
        'company_id' => $company->id,
        'customer_id' => $supplier->id,
        'currency' => 'EUR',
        'status' => 'confirmed',
    ]);

    $po_line = PurchaseOrderLine::query()->create([
        'purchase_order_id' => $po->id,
        'item_id' => $item->id,
        'name' => 'Nut',
        'qty_ordered' => 4,
        'qty_received' => 0,
    ]);

    $receipt = GoodsReceipt::query()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $po->id,
    ]);

    GoodsReceiptLine::query()->create([
        'company_id' => $company->id,
        'goods_receipt_id' => $receipt->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'unit_cost' => '1.0000',
        'purchase_order_line_id' => $po_line->id,
    ]);

    expect(fn () => $receipt->update(['posted_at' => now()]))
        ->toThrow(ValidationException::class);

    expect($receipt->fresh()->posted_at)->toBeNull()
        ->and($receipt->fresh()->inventory_posted_at)->toBeNull();
});
