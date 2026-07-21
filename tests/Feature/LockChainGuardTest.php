<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Models\Warehouse;

uses(RefreshDatabase::class);

it('locks a delivered sales order line while allowing operational counters', function (): void {
    $company = Company::query()->create([
        'slug' => 'lock-chain-' . uniqid(),
        'name' => 'Lock Chain Company',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Lock Chain Customer',
        'is_customer' => true,
    ]);
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Locked Widget',
        'sku' => 'LOCK-1',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'Main Warehouse',
        'code' => 'MAIN',
    ]);
    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::Confirmed,
    ]);
    $line = SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'item_id' => $item->id,
        'name' => 'Locked Widget',
        'qty_ordered' => '5.0000',
        'unit_price' => '12.5000',
        'status' => SalesOrderLineStatus::Open,
    ]);
    $delivery_note = DeliveryNote::query()->create([
        'company_id' => $company->id,
        'sales_order_id' => $order->id,
        'reference' => 'DDT-LOCK-1',
    ]);

    DeliveryNoteLine::query()->create([
        'company_id' => $company->id,
        'delivery_note_id' => $delivery_note->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '1.0000',
        'sales_order_line_id' => $line->id,
    ]);

    $line->refresh();

    expect($line->locked_at)->not->toBeNull()
        ->and(fn () => $line->update(['unit_price' => '13.0000']))
        ->toThrow(ValidationException::class)
        ->and(fn () => $line->update(['qty_ordered' => '6.0000']))
        ->toThrow(ValidationException::class)
        ->and(fn () => $line->delete())
        ->toThrow(ValidationException::class);

    $line->refresh();
    $line->update([
        'qty_delivered' => '1.0000',
        'status' => SalesOrderLineStatus::PartiallyEvased,
    ]);

    expect((string) $line->fresh()->qty_delivered)->toBe('1.0000')
        ->and($line->fresh()->status)->toBe(SalesOrderLineStatus::PartiallyEvased);
});
