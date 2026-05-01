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
use Modules\ERP\Models\StockMovement;
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
    $po->refresh();
    $level = StockLevel::query()
        ->where('company_id', $company->id)
        ->where('item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();

    expect($po_line->qty_received)->toBe(12)
        ->and($po->status)->toBe('partial')
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

it('does not duplicate inbound stock on subsequent updates after posting', function (): void {
    $company = Company::query()->create([
        'slug' => 'gr-idem',
        'name' => 'Gr Idem',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'WH',
        'code' => 'GR-ID',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Bolt',
        'sku' => 'B-ID',
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
        'qty_ordered' => 8,
        'qty_received' => 0,
    ]);

    $receipt = GoodsReceipt::query()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $po->id,
        'reference' => 'GR-IDEM',
    ]);

    GoodsReceiptLine::query()->create([
        'company_id' => $company->id,
        'goods_receipt_id' => $receipt->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 8,
        'unit_cost' => '4.0000',
        'purchase_order_line_id' => $po_line->id,
    ]);

    $receipt->update(['posted_at' => now()]);

    $stock_after_post = StockLevel::query()
        ->where('company_id', $company->id)
        ->where('item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();
    $inbound_count_after_post = StockMovement::query()
        ->where('company_id', $company->id)
        ->where('direction', 'in')
        ->count();

    $receipt->update(['notes' => 'metadata update']);

    $stock_after_metadata_update = StockLevel::query()
        ->where('company_id', $company->id)
        ->where('item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();
    $inbound_count_after_metadata_update = StockMovement::query()
        ->where('company_id', $company->id)
        ->where('direction', 'in')
        ->count();

    expect($stock_after_post->quantity)->toBe(8)
        ->and($stock_after_metadata_update->quantity)->toBe(8)
        ->and($inbound_count_after_post)->toBe(1)
        ->and($inbound_count_after_metadata_update)->toBe(1);
});

it('rejects posting when line item belongs to another company', function (): void {
    $company = Company::query()->create([
        'slug' => 'gr-co1',
        'name' => 'Gr Co1',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $other_company = Company::query()->create([
        'slug' => 'gr-co2',
        'name' => 'Gr Co2',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'WH',
        'code' => 'WH-C1',
    ]);

    $item = Item::query()->create([
        'company_id' => $other_company->id,
        'name' => 'External',
        'sku' => 'EXT',
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

    $receipt = GoodsReceipt::query()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $po->id,
    ]);

    GoodsReceiptLine::query()->create([
        'company_id' => $company->id,
        'goods_receipt_id' => $receipt->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'unit_cost' => '1.0000',
    ]);

    expect(fn () => $receipt->update(['posted_at' => now()]))
        ->toThrow(ValidationException::class);
});

it('marks purchase order as received when all lines are fully received', function (): void {
    $company = Company::query()->create([
        'slug' => 'gr-full',
        'name' => 'Gr Full',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'WH',
        'code' => 'WH-FULL',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Pin',
        'sku' => 'PIN',
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
        'name' => 'Pin',
        'qty_ordered' => 5,
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
        'quantity' => 5,
        'unit_cost' => '2.0000',
        'purchase_order_line_id' => $po_line->id,
    ]);

    $receipt->update(['posted_at' => now()]);

    expect($po->fresh()->status)->toBe('received')
        ->and($po_line->fresh()->qty_received)->toBe(5);
});
