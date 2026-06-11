<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DeliveryNoteDirection;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Casts\StockMovementDirection;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\ReturnOrder;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\StockMovement;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Returns\ReturnOrderService;

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

it('approves customer returns from draft', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Draft,
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'unit_cost' => '1.0000',
    ]);

    $approved = app(ReturnOrderService::class)->approve($return_order);

    expect($approved->status)->toBe(ReturnStatus::Approved);
});

it('rejects approval when return party is not a customer', function (): void {
    [$company, , $warehouse, $item] = createReturnOrderFixtures();
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Supplier only',
        'is_customer' => false,
        'is_supplier' => true,
    ]);

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Draft,
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'unit_cost' => '1.0000',
    ]);

    expect(fn () => app(ReturnOrderService::class)->approve($return_order))
        ->toThrow(ValidationException::class);
});

it('records inbound stock when completing approved customer returns', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Approved,
    ]);
    $return_line = $return_order->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 3,
        'unit_cost' => '12.5000',
    ]);

    $processed = app(ReturnOrderService::class)->complete($return_order);

    $level = StockLevel::query()
        ->where('company_id', $company->id)
        ->where('item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();

    expect($processed->status)->toBe(ReturnStatus::Processed)
        ->and($processed->processed_at)->not->toBeNull()
        ->and($processed->delivery_note_id)->not->toBeNull()
        ->and((string) $level->quantity)->toBe('3.0000')
        ->and((float) $level->weighted_avg_cost)->toBe(12.5);

    $delivery_note = $processed->delivery_note()->firstOrFail();
    $delivery_note_line = $delivery_note->lines()->firstOrFail();
    $movement = StockMovement::query()
        ->where('source_type', (new DeliveryNoteLine)->getMorphClass())
        ->where('source_id', $delivery_note_line->id)
        ->firstOrFail();

    expect($delivery_note->direction)->toBe(DeliveryNoteDirection::Inbound)
        ->and($delivery_note->posted_at)->not->toBeNull()
        ->and($delivery_note->inventory_posted_at)->not->toBeNull()
        ->and($return_line->fresh()->delivery_note_line_id)->toBe($delivery_note_line->id)
        ->and($movement->direction)->toBe(StockMovementDirection::In);
});

it('tracks returned quantities on customer source lines', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();

    $sales_order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::Confirmed,
    ]);
    $sales_order_line = SalesOrderLine::query()->create([
        'sales_order_id' => $sales_order->id,
        'item_id' => $item->id,
        'name' => 'Returned item',
        'qty_ordered' => 5,
        'qty_delivered' => 5,
        'qty_invoiced' => 5,
        'status' => SalesOrderLineStatus::FullyEvased,
    ]);
    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $invoice_line = $invoice->lines()->create([
        'line_no' => 1,
        'sales_order_line_id' => $sales_order_line->id,
        'description' => 'Returned item',
        'quantity' => 5,
        'unit_price' => 10,
    ]);
    $source_delivery_note = DeliveryNote::query()->create([
        'company_id' => $company->id,
        'direction' => DeliveryNoteDirection::Outbound,
        'reference' => 'DN-ORIGINAL',
        'delivered_at' => now(),
    ]);
    $source_delivery_note_line = $source_delivery_note->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'sales_order_line_id' => $sales_order_line->id,
    ]);
    StockMovement::query()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'direction' => StockMovementDirection::Out,
        'quantity' => 5,
        'unit_cost' => '12.5000',
        'source_type' => (new DeliveryNoteLine)->getMorphClass(),
        'source_id' => $source_delivery_note_line->id,
    ]);
    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'invoice_id' => $invoice->id,
        'status' => ReturnStatus::Approved,
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'invoice_line_id' => $invoice_line->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 2,
        'unit_cost' => '12.5000',
    ]);

    app(ReturnOrderService::class)->complete($return_order);

    expect((string) $invoice_line->fresh()->qty_returned)->toBe('2.0000')
        ->and((string) $sales_order_line->fresh()->qty_returned)->toBe('2.0000');
});

it('rejects completing customer returns before approval', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Draft,
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'unit_cost' => '1.0000',
    ]);

    expect(fn () => app(ReturnOrderService::class)->complete($return_order))
        ->toThrow(ValidationException::class);
});

it('prevents completing the same customer return twice', function (): void {
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

    $service = app(ReturnOrderService::class);
    $service->complete($return_order);

    expect(fn () => $service->complete($return_order->fresh()))
        ->toThrow(ValidationException::class);
});

it('cancels draft customer returns', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Draft,
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'unit_cost' => '1.0000',
    ]);

    $cancelled = app(ReturnOrderService::class)->cancel($return_order);

    expect($cancelled->status)->toBe(ReturnStatus::Cancelled);
});

it('rejects cancelling processed customer returns', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Processed,
        'processed_at' => now(),
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'unit_cost' => '1.0000',
    ]);

    expect(fn () => app(ReturnOrderService::class)->cancel($return_order))
        ->toThrow(ValidationException::class);
});
