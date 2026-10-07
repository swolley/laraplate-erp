<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Inventory\StockReservationService;
use Modules\ERP\Services\SalesOrders\SalesOrderAmendmentService;
use Modules\ERP\Services\SalesOrders\SalesOrderEvasionService;

uses(RefreshDatabase::class);

const REVERT_SOURCE = 'erp.sales_order_line';

function revert_on_hand(Company $company, Item $item, string $quantity): StockLevel
{
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

    return StockLevel::query()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => $quantity,
        'weighted_avg_cost' => '1.0000',
    ]);
}

function revert_line(SalesOrder $order, Item $item, string $quantity): SalesOrderLine
{
    return SalesOrderLine::factory()->for($order, 'sales_order')->create([
        'item_id' => $item->id,
        'qty_ordered' => $quantity,
    ]);
}

beforeEach(function (): void {
    $this->company = Company::factory()->create();
    $this->item = Item::factory()->create(['company_id' => $this->company->id]);
    revert_on_hand($this->company, $this->item, '10.0000');
    $this->reservations = app(StockReservationService::class);
    $this->source = SalesOrder::factory()->create(['company_id' => $this->company->id]);
    $this->sourceLine = revert_line($this->source, $this->item, '4.0000');
    $this->source->update(['status' => SalesOrderStatus::Confirmed]);
});

it('cancelling an amendment reverts a Confirmed source and re-reserves its remaining quantity', function (): void {
    // The confirmed source holds its full quantity.
    expect($this->reservations->reservedQuantity(REVERT_SOURCE, $this->sourceLine->id))->toBe('4.0000');

    $amendment = app(SalesOrderAmendmentService::class)->amend($this->source->fresh());
    $amendmentLine = $amendment->lines->first();
    $amendment->update(['status' => SalesOrderStatus::Confirmed]);

    // The amendment superseded the source: the source is Amended and holds nothing anymore.
    expect($this->source->fresh()->status)->toBe(SalesOrderStatus::Amended)
        ->and($this->reservations->reservedQuantity(REVERT_SOURCE, $this->sourceLine->id))->toBe('0.0000')
        ->and($this->reservations->reservedQuantity(REVERT_SOURCE, $amendmentLine->id))->toBe('4.0000');

    $amendment->update(['status' => SalesOrderStatus::Cancelled]);

    // Cancelling the amendment reverts the source to Confirmed and re-reserves its remaining 4;
    // the amendment's own hold is released by the existing cancel handling.
    expect($this->source->fresh()->status)->toBe(SalesOrderStatus::Confirmed)
        ->and($this->reservations->reservedQuantity(REVERT_SOURCE, $this->sourceLine->id))->toBe('4.0000')
        ->and($this->reservations->reservedQuantity(REVERT_SOURCE, $amendmentLine->id))->toBe('0.0000');
});

it('cancelling an amendment of a partially evased source reverts it to PartiallyEvased', function (): void {
    // Deliver part of the source, so it is partially evased before being superseded.
    app(SalesOrderEvasionService::class)->registerDelivery($this->source->fresh(), [$this->sourceLine->id => '2.0000']);

    expect($this->source->fresh()->status)->toBe(SalesOrderStatus::PartiallyEvased);

    $amendment = app(SalesOrderAmendmentService::class)->amend($this->source->fresh());
    $amendmentLine = $amendment->lines->first();
    $amendment->update(['status' => SalesOrderStatus::Confirmed]);

    expect($this->source->fresh()->status)->toBe(SalesOrderStatus::Amended)
        ->and($this->reservations->reservedQuantity(REVERT_SOURCE, $this->sourceLine->id))->toBe('0.0000');

    $amendment->update(['status' => SalesOrderStatus::Cancelled]);

    // The source returns to PartiallyEvased (it still carries the delivered 2) and re-reserves only
    // its remaining 2 (ordered 4 minus delivered 2), not the full ordered quantity.
    expect($this->source->fresh()->status)->toBe(SalesOrderStatus::PartiallyEvased)
        ->and($this->reservations->reservedQuantity(REVERT_SOURCE, $this->sourceLine->id))->toBe('2.0000')
        ->and($this->reservations->reservedQuantity(REVERT_SOURCE, $amendmentLine->id))->toBe('0.0000');
});
