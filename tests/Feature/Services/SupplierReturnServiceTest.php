<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DeliveryNoteDirection;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Casts\StockMovementDirection;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\GoodsReceipt;
use Modules\ERP\Models\GoodsReceiptLine;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\StockMovement;
use Modules\ERP\Models\SupplierReturn;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Inventory\StockMovementService;
use Modules\ERP\Services\Returns\SupplierReturnService;

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

it('approves supplier returns from draft', function (): void {
    [$company, $party, $warehouse, $item] = createSupplierReturnFixtures();

    $supplier_return = SupplierReturn::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Draft,
    ]);
    $supplier_return->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
    ]);

    $approved = app(SupplierReturnService::class)->approve($supplier_return);

    expect($approved->status)->toBe(ReturnStatus::Approved);
});

it('rejects approval when return party is not a supplier', function (): void {
    [$company, , $warehouse, $item] = createSupplierReturnFixtures();
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Customer only',
        'is_customer' => true,
    ]);

    $supplier_return = SupplierReturn::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Draft,
    ]);
    $supplier_return->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
    ]);

    expect(fn () => app(SupplierReturnService::class)->approve($supplier_return))
        ->toThrow(ValidationException::class);
});

it('records outbound stock when completing approved supplier returns', function (): void {
    [$company, $party, $warehouse, $item] = createSupplierReturnFixtures();

    $supplier_return = SupplierReturn::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Approved,
    ]);
    $supplier_return_line = $supplier_return->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 2,
    ]);

    $processed = app(SupplierReturnService::class)->complete($supplier_return);

    $level = StockLevel::query()
        ->where('company_id', $company->id)
        ->where('item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();

    expect($processed->status)->toBe(ReturnStatus::Processed)
        ->and($processed->processed_at)->not->toBeNull()
        ->and($processed->delivery_note_id)->not->toBeNull()
        ->and((string) $level->quantity)->toBe('3.0000');

    $delivery_note = $processed->delivery_note()->firstOrFail();
    $delivery_note_line = $delivery_note->lines()->firstOrFail();
    $movement = StockMovement::query()
        ->where('source_type', (new DeliveryNoteLine)->getMorphClass())
        ->where('source_id', $delivery_note_line->id)
        ->where('direction', StockMovementDirection::Out)
        ->firstOrFail();

    expect($delivery_note->direction)->toBe(DeliveryNoteDirection::Outbound)
        ->and($delivery_note->posted_at)->not->toBeNull()
        ->and($delivery_note->inventory_posted_at)->not->toBeNull()
        ->and($delivery_note->cogs_journal_entry_id)->toBeNull()
        ->and($supplier_return_line->fresh()->delivery_note_line_id)->toBe($delivery_note_line->id)
        ->and((string) $movement->quantity)->toBe('2.0000');
});

it('tracks returned quantities on supplier source lines', function (): void {
    [$company, $party, $warehouse, $item] = createSupplierReturnFixtures();

    $purchase_order = PurchaseOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => 'received',
    ]);
    $purchase_order_line = PurchaseOrderLine::query()->create([
        'purchase_order_id' => $purchase_order->id,
        'item_id' => $item->id,
        'name' => 'Supplier item',
        'qty_ordered' => 5,
        'qty_received' => 5,
    ]);
    $goods_receipt = GoodsReceipt::query()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchase_order->id,
        'reference' => 'GR-RET-1',
    ]);
    $goods_receipt_line = GoodsReceiptLine::query()->create([
        'company_id' => $company->id,
        'goods_receipt_id' => $goods_receipt->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'unit_cost' => '10.0000',
        'purchase_order_line_id' => $purchase_order_line->id,
    ]);
    $supplier_return = SupplierReturn::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'purchase_order_id' => $purchase_order->id,
        'status' => ReturnStatus::Approved,
    ]);
    $supplier_return->lines()->create([
        'company_id' => $company->id,
        'purchase_order_line_id' => $purchase_order_line->id,
        'goods_receipt_line_id' => $goods_receipt_line->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 2,
    ]);

    app(SupplierReturnService::class)->complete($supplier_return);

    expect((string) $purchase_order_line->fresh()->qty_returned)->toBe('2.0000')
        ->and((string) $goods_receipt_line->fresh()->qty_returned)->toBe('2.0000');
});

it('rejects completing supplier returns before approval', function (): void {
    [$company, $party, $warehouse, $item] = createSupplierReturnFixtures();

    $supplier_return = SupplierReturn::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Draft,
    ]);
    $supplier_return->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
    ]);

    expect(fn () => app(SupplierReturnService::class)->complete($supplier_return))
        ->toThrow(ValidationException::class);
});

it('cancels draft supplier returns', function (): void {
    [$company, $party, $warehouse, $item] = createSupplierReturnFixtures();

    $supplier_return = SupplierReturn::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Draft,
    ]);
    $supplier_return->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
    ]);

    $cancelled = app(SupplierReturnService::class)->cancel($supplier_return);

    expect($cancelled->status)->toBe(ReturnStatus::Cancelled);
});
