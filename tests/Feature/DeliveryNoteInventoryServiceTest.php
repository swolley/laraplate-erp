<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Customer;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\StockMovement;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Inventory\StockMovementService;

uses(RefreshDatabase::class);

it('posts outbound stock and updates sales order lines when posted_at is set', function (): void {
    $company = Company::query()->create([
        'slug' => 'ddn-inv',
        'name' => 'Ddn Inv',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'WH',
        'code' => 'WH1',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Part',
        'sku' => 'P-1',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    $stock = app(StockMovementService::class);
    $stock->recordInbound($company->id, $item->id, $warehouse->id, 100, '2.0000');

    $customer = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::CONFIRMED,
    ]);

    $so_line = SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'item_id' => $item->id,
        'name' => 'Part',
        'qty_ordered' => 20,
        'qty_delivered' => 0,
        'qty_invoiced' => 0,
        'status' => SalesOrderLineStatus::OPEN,
    ]);

    $note = DeliveryNote::query()->create([
        'company_id' => $company->id,
        'sales_order_id' => $order->id,
        'reference' => 'DDT-1',
    ]);

    DeliveryNoteLine::query()->create([
        'company_id' => $company->id,
        'delivery_note_id' => $note->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 7,
        'sales_order_line_id' => $so_line->id,
    ]);

    $note->update(['posted_at' => now()]);

    $so_line->refresh();
    $level = StockLevel::query()
        ->where('company_id', $company->id)
        ->where('item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();

    expect($so_line->qty_delivered)->toBe(7)
        ->and($level->quantity)->toBe(93)
        ->and($note->fresh()->inventory_posted_at)->not->toBeNull()
        ->and($note->fresh()->posted_at)->not->toBeNull();
});

it('rejects delivery quantity above remaining on the sales order line', function (): void {
    $company = Company::query()->create([
        'slug' => 'ddn-over',
        'name' => 'Ddn Over',
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
        'name' => 'X',
        'sku' => 'X-1',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    app(StockMovementService::class)->recordInbound($company->id, $item->id, $warehouse->id, 50, '1.0000');

    $customer = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'C',
    ]);

    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::CONFIRMED,
    ]);

    $so_line = SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'item_id' => $item->id,
        'name' => 'X',
        'qty_ordered' => 3,
        'qty_delivered' => 0,
        'qty_invoiced' => 0,
        'status' => SalesOrderLineStatus::OPEN,
    ]);

    $note = DeliveryNote::query()->create([
        'company_id' => $company->id,
        'sales_order_id' => $order->id,
    ]);

    DeliveryNoteLine::query()->create([
        'company_id' => $company->id,
        'delivery_note_id' => $note->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'sales_order_line_id' => $so_line->id,
    ]);

    expect(fn () => $note->update(['posted_at' => now()]))
        ->toThrow(ValidationException::class);

    expect($note->fresh()->posted_at)->toBeNull()
        ->and($note->fresh()->inventory_posted_at)->toBeNull();
});

it('does not duplicate outbound stock on subsequent updates after posting', function (): void {
    $company = Company::query()->create([
        'slug' => 'ddn-idem',
        'name' => 'Ddn Idem',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'WH',
        'code' => 'WH-ID',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Part',
        'sku' => 'P-ID',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    app(StockMovementService::class)->recordInbound($company->id, $item->id, $warehouse->id, 20, '2.0000');

    $customer = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::CONFIRMED,
    ]);

    $so_line = SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'item_id' => $item->id,
        'name' => 'Part',
        'qty_ordered' => 5,
        'qty_delivered' => 0,
        'qty_invoiced' => 0,
        'status' => SalesOrderLineStatus::OPEN,
    ]);

    $note = DeliveryNote::query()->create([
        'company_id' => $company->id,
        'sales_order_id' => $order->id,
        'reference' => 'DDT-IDEM',
    ]);

    DeliveryNoteLine::query()->create([
        'company_id' => $company->id,
        'delivery_note_id' => $note->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'sales_order_line_id' => $so_line->id,
    ]);

    $note->update(['posted_at' => now()]);

    $stock_after_post = StockLevel::query()
        ->where('company_id', $company->id)
        ->where('item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();
    $outbound_count_after_post = StockMovement::query()
        ->where('company_id', $company->id)
        ->where('direction', 'out')
        ->count();

    $note->update(['notes' => 'metadata update']);

    $stock_after_metadata_update = StockLevel::query()
        ->where('company_id', $company->id)
        ->where('item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();
    $outbound_count_after_metadata_update = StockMovement::query()
        ->where('company_id', $company->id)
        ->where('direction', 'out')
        ->count();

    expect($stock_after_post->quantity)->toBe(15)
        ->and($stock_after_metadata_update->quantity)->toBe(15)
        ->and($outbound_count_after_post)->toBe(1)
        ->and($outbound_count_after_metadata_update)->toBe(1);
});

it('rejects posting when line warehouse belongs to another company', function (): void {
    $company = Company::query()->create([
        'slug' => 'ddn-co1',
        'name' => 'Ddn Co1',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $other_company = Company::query()->create([
        'slug' => 'ddn-co2',
        'name' => 'Ddn Co2',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $other_company->id,
        'name' => 'Other WH',
        'code' => 'OTH',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Part',
        'sku' => 'P-CO',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    $customer = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::CONFIRMED,
    ]);

    $so_line = SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'item_id' => $item->id,
        'name' => 'Part',
        'qty_ordered' => 2,
        'qty_delivered' => 0,
        'qty_invoiced' => 0,
        'status' => SalesOrderLineStatus::OPEN,
    ]);

    $note = DeliveryNote::query()->create([
        'company_id' => $company->id,
        'sales_order_id' => $order->id,
    ]);

    DeliveryNoteLine::query()->create([
        'company_id' => $company->id,
        'delivery_note_id' => $note->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'sales_order_line_id' => $so_line->id,
    ]);

    expect(fn () => $note->update(['posted_at' => now()]))
        ->toThrow(ValidationException::class);
});

it('posts a balanced COGS journal linked to the delivery note when inventory posts', function (): void {
    $company = Company::query()->create([
        'slug' => 'ddn-cogs',
        'name' => 'Ddn Cogs',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'WH',
        'code' => 'WH-COGS',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Part',
        'sku' => 'P-COGS',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    app(StockMovementService::class)
        ->recordInbound($company->id, $item->id, $warehouse->id, 100, '2.0000');

    $customer = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::CONFIRMED,
    ]);

    $so_line = SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'item_id' => $item->id,
        'name' => 'Part',
        'qty_ordered' => 20,
        'qty_delivered' => 0,
        'qty_invoiced' => 0,
        'status' => SalesOrderLineStatus::OPEN,
    ]);

    $note = DeliveryNote::query()->create([
        'company_id' => $company->id,
        'sales_order_id' => $order->id,
        'reference' => 'DDT-COGS',
    ]);

    DeliveryNoteLine::query()->create([
        'company_id' => $company->id,
        'delivery_note_id' => $note->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 7,
        'sales_order_line_id' => $so_line->id,
    ]);

    $note->update(['posted_at' => now()]);
    $note->refresh();

    expect($note->cogs_journal_entry_id)->not->toBeNull();

    $entry = JournalEntry::query()->withoutGlobalScopes()->findOrFail((int) $note->cogs_journal_entry_id);

    expect((string) $entry->reference_type)->toBe((new DeliveryNote)->getMorphClass())
        ->and((int) $entry->reference_id)->toBe((int) $note->id)
        ->and($entry->lines)->toHaveCount(2);

    $sum_local = 0.0;

    foreach ($entry->lines as $jel) {
        $sum_local += (float) $jel->amount_local;
    }

    expect($sum_local)->toBe(0.0);
});
