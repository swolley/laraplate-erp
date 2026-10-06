<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Enums\StockReservationState;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\StockReservation;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Inventory\StockReservationService;
use Modules\ERP\Services\SalesOrders\SalesOrderAmendmentService;
use Modules\ERP\Services\SalesOrders\SalesOrderEvasionService;

uses(RefreshDatabase::class);

const LIFECYCLE_SOURCE = 'erp.sales_order_line';

function lifecycle_on_hand(Company $company, Item $item, string $quantity): StockLevel
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

function lifecycle_line(SalesOrder $order, Item $item, string $quantity): SalesOrderLine
{
    return SalesOrderLine::factory()->for($order, 'sales_order')->create([
        'item_id' => $item->id,
        'qty_ordered' => $quantity,
    ]);
}

beforeEach(function (): void {
    $this->company = Company::factory()->create();
    $this->order = SalesOrder::factory()->create(['company_id' => $this->company->id]);
    $this->service = app(StockReservationService::class);
});

it('cancelling a confirmed order releases its reservations', function (): void {
    $item = Item::factory()->create(['company_id' => $this->company->id]);
    lifecycle_on_hand($this->company, $item, '10.0000');
    $line = lifecycle_line($this->order, $item, '4.0000');

    $this->order->update(['status' => SalesOrderStatus::Confirmed]);

    expect($this->service->available($this->company->id, $item->id))->toBe('6.0000');

    $this->order->update(['status' => SalesOrderStatus::Cancelled]);

    expect($this->service->available($this->company->id, $item->id))->toBe('10.0000')
        ->and($this->service->reservedQuantity(LIFECYCLE_SOURCE, $line->id))->toBe('0.0000')
        ->and(StockReservation::query()->where('source_id', $line->id)->active()->count())->toBe(0);
});

it('evasion consumes the shipped quantity and keeps the remainder reserved', function (): void {
    $item = Item::factory()->create(['company_id' => $this->company->id]);
    lifecycle_on_hand($this->company, $item, '10.0000');
    $line = lifecycle_line($this->order, $item, '10.0000');

    $this->order->update(['status' => SalesOrderStatus::Confirmed]);

    expect($this->service->reservedQuantity(LIFECYCLE_SOURCE, $line->id))->toBe('10.0000');

    app(SalesOrderEvasionService::class)->registerDelivery($this->order, [$line->id => '4.0000']);

    $consumed = StockReservation::query()
        ->where('source_id', $line->id)
        ->where('state', StockReservationState::Consumed->value)
        ->sum('quantity');

    expect($this->service->reservedQuantity(LIFECYCLE_SOURCE, $line->id))->toBe('6.0000')
        ->and((float) $consumed)->toBe(4.0)
        ->and($this->service->available($this->company->id, $item->id))->toBe('4.0000')
        ->and((string) $line->fresh()->qty_delivered)->toBe('4.0000');
});

it('evasion of a partially backordered line consumes only the reserved part', function (): void {
    $item = Item::factory()->create(['company_id' => $this->company->id]);
    lifecycle_on_hand($this->company, $item, '6.0000');
    $line = lifecycle_line($this->order, $item, '10.0000');

    $this->order->update(['status' => SalesOrderStatus::Confirmed]);

    expect($this->service->reservedQuantity(LIFECYCLE_SOURCE, $line->id))->toBe('6.0000');

    // Ships the whole ordered quantity, 4 of which were never reserved (backorder).
    app(SalesOrderEvasionService::class)->registerDelivery($this->order, [$line->id => '10.0000']);

    $consumed = StockReservation::query()
        ->where('source_id', $line->id)
        ->where('state', StockReservationState::Consumed->value)
        ->sum('quantity');

    expect($this->service->reservedQuantity(LIFECYCLE_SOURCE, $line->id))->toBe('0.0000')
        ->and((float) $consumed)->toBe(6.0)
        ->and($this->service->available($this->company->id, $item->id))->toBe('6.0000')
        ->and((string) $line->fresh()->qty_delivered)->toBe('10.0000');
});

it('amend-down below consumed quantity is clamped', function (): void {
    $item = Item::factory()->create(['company_id' => $this->company->id]);
    lifecycle_on_hand($this->company, $item, '10.0000');
    $line = lifecycle_line($this->order, $item, '10.0000');

    $this->order->update(['status' => SalesOrderStatus::Confirmed]);

    // Partial evasion ships 7, consuming 7 of the 10 hard reserved (3 remain hard).
    app(SalesOrderEvasionService::class)->registerDelivery($this->order, [$line->id => '7.0000']);

    expect($this->service->reservedQuantity(LIFECYCLE_SOURCE, $line->id))->toBe('3.0000');

    app(SalesOrderAmendmentService::class)->amend($this->order->fresh());

    $consumed = StockReservation::query()
        ->where('source_id', $line->id)
        ->where('state', StockReservationState::Consumed->value)
        ->sum('quantity');

    // amend() no longer releases the source's hold: the remaining 3 stay hard-reserved on the
    // source (released only when the amendment confirms), the 7 consumed rows are terminal and
    // untouched, and no negative reservation is ever produced.
    expect($this->service->reservedQuantity(LIFECYCLE_SOURCE, $line->id))->toBe('3.0000')
        ->and((float) $consumed)->toBe(7.0)
        ->and($this->service->available($this->company->id, $item->id))->toBe('7.0000')
        ->and(StockReservation::query()->where('quantity', '<', 0)->count())->toBe(0);
});

it('amend() does not release the source order reservation', function (): void {
    $item = Item::factory()->create(['company_id' => $this->company->id]);
    lifecycle_on_hand($this->company, $item, '10.0000');
    $line = lifecycle_line($this->order, $item, '4.0000');

    $this->order->update(['status' => SalesOrderStatus::Confirmed]);

    expect($this->service->reservedQuantity(LIFECYCLE_SOURCE, $line->id))->toBe('4.0000');

    app(SalesOrderAmendmentService::class)->amend($this->order->fresh());

    expect($this->service->reservedQuantity(LIFECYCLE_SOURCE, $line->id))->toBe('4.0000')
        ->and($this->service->available($this->company->id, $item->id))->toBe('6.0000');
});

it('an abandoned amendment leaves the source hold intact', function (): void {
    $item = Item::factory()->create(['company_id' => $this->company->id]);
    lifecycle_on_hand($this->company, $item, '10.0000');
    $line = lifecycle_line($this->order, $item, '4.0000');

    $this->order->update(['status' => SalesOrderStatus::Confirmed]);

    // Drafted and never confirmed: the amendment is abandoned.
    $amendment = app(SalesOrderAmendmentService::class)->amend($this->order->fresh());
    $amendment_line = $amendment->lines->first();

    expect($this->service->reservedQuantity(LIFECYCLE_SOURCE, $line->id))->toBe('4.0000')
        ->and($this->service->reservedQuantity(LIFECYCLE_SOURCE, $amendment_line->id))->toBe('0.0000')
        ->and($this->service->available($this->company->id, $item->id))->toBe('6.0000');
});

it('confirming the amendment releases the source holds and reserves the amendment lines', function (): void {
    $item = Item::factory()->create(['company_id' => $this->company->id]);
    lifecycle_on_hand($this->company, $item, '10.0000');
    $line = lifecycle_line($this->order, $item, '4.0000');

    $this->order->update(['status' => SalesOrderStatus::Confirmed]);

    expect($this->service->reservedQuantity(LIFECYCLE_SOURCE, $line->id))->toBe('4.0000');

    $amendment = app(SalesOrderAmendmentService::class)->amend($this->order->fresh());
    $amendment_line = $amendment->lines->first();

    $amendment->update(['status' => SalesOrderStatus::Confirmed]);

    // Net: the remaining 4 is reserved exactly once, by the amendment; the source holds nothing.
    expect($this->service->reservedQuantity(LIFECYCLE_SOURCE, $line->id))->toBe('0.0000')
        ->and($this->service->reservedQuantity(LIFECYCLE_SOURCE, $amendment_line->id))->toBe('4.0000')
        ->and($this->service->available($this->company->id, $item->id))->toBe('6.0000');
});

it('releases the source before reserving so the amendment fully reserves when on_hand equals the remaining', function (): void {
    $item = Item::factory()->create(['company_id' => $this->company->id]);
    lifecycle_on_hand($this->company, $item, '4.0000');
    $line = lifecycle_line($this->order, $item, '4.0000');

    $this->order->update(['status' => SalesOrderStatus::Confirmed]);

    // The source holds all 4 units; nothing is available for a reserve-first ordering to grab.
    expect($this->service->reservedQuantity(LIFECYCLE_SOURCE, $line->id))->toBe('4.0000')
        ->and($this->service->available($this->company->id, $item->id))->toBe('0.0000');

    $amendment = app(SalesOrderAmendmentService::class)->amend($this->order->fresh());
    $amendment_line = $amendment->lines->first();

    $amendment->update(['status' => SalesOrderStatus::Confirmed]);

    // Release-before-reserve frees the source's 4 first, so the amendment reserves the full 4
    // (fully reserved, not backordered) even though on_hand exactly equals the remaining quantity.
    expect($this->service->reservedQuantity(LIFECYCLE_SOURCE, $amendment_line->id))->toBe('4.0000')
        ->and($this->service->reservedQuantity(LIFECYCLE_SOURCE, $line->id))->toBe('0.0000')
        ->and($this->service->available($this->company->id, $item->id))->toBe('0.0000');
});
