<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Enums\StockReservationState;
use Modules\ERP\Exceptions\InsufficientStockException;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\StockReservation;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Inventory\StockReservationService;

uses(RefreshDatabase::class);

const RESERVE_ON_CONFIRM_SOURCE = 'erp.sales_order_line';

/**
 * Seeds one on-hand row for the item in a fresh warehouse of the same company.
 */
function reserve_on_confirm_on_hand(Company $company, Item $item, string $quantity): StockLevel
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

function reserve_on_confirm_line(SalesOrder $order, ?Item $item, string $quantity): SalesOrderLine
{
    return SalesOrderLine::factory()->for($order, 'sales_order')->create([
        'item_id' => $item?->id,
        'qty_ordered' => $quantity,
    ]);
}

beforeEach(function (): void {
    $this->company = Company::factory()->create();
    $this->order = SalesOrder::factory()->create(['company_id' => $this->company->id]);
    $this->service = app(StockReservationService::class);
});

it('confirming a sales order hard-reserves each item-backed line', function (): void {
    $first_item = Item::factory()->create(['company_id' => $this->company->id]);
    $second_item = Item::factory()->create(['company_id' => $this->company->id]);
    reserve_on_confirm_on_hand($this->company, $first_item, '10.0000');
    reserve_on_confirm_on_hand($this->company, $second_item, '20.0000');

    $first_line = reserve_on_confirm_line($this->order, $first_item, '4.0000');
    $second_line = reserve_on_confirm_line($this->order, $second_item, '7.5000');

    $this->order->update(['status' => SalesOrderStatus::Confirmed]);

    expect($this->service->available($this->company->id, $first_item->id))->toBe('6.0000')
        ->and($this->service->available($this->company->id, $second_item->id))->toBe('12.5000');

    $reservations = StockReservation::query()->where('source_type', RESERVE_ON_CONFIRM_SOURCE)->orderBy('source_id')->get();

    expect($reservations)->toHaveCount(2)
        ->and($reservations->pluck('source_id')->all())->toBe([$first_line->id, $second_line->id])
        ->and($reservations->every(fn (StockReservation $row): bool => $row->state === StockReservationState::Hard))->toBeTrue()
        ->and($reservations->every(fn (StockReservation $row): bool => $row->company_id === $this->company->id))->toBeTrue();
});

it('reserves nothing for item-less lines', function (): void {
    $item = Item::factory()->create(['company_id' => $this->company->id]);
    reserve_on_confirm_on_hand($this->company, $item, '5.0000');

    reserve_on_confirm_line($this->order, null, '3.0000');
    reserve_on_confirm_line($this->order, $item, '2.0000');

    $this->order->update(['status' => SalesOrderStatus::Confirmed]);

    expect(StockReservation::query()->count())->toBe(1)
        ->and($this->service->available($this->company->id, $item->id))->toBe('3.0000');
});

it('promotes a live soft hold to hard without reserving the line twice', function (): void {
    $item = Item::factory()->create(['company_id' => $this->company->id]);
    reserve_on_confirm_on_hand($this->company, $item, '10.0000');
    $line = reserve_on_confirm_line($this->order, $item, '4.0000');

    $this->service->reserve(
        $this->company->id,
        $item->id,
        '4.0000',
        StockReservationState::Soft,
        RESERVE_ON_CONFIRM_SOURCE,
        $line->id,
        null,
        now()->addHour(),
    );

    $this->order->update(['status' => SalesOrderStatus::Confirmed]);

    $reservations = StockReservation::query()->where('source_id', $line->id)->get();

    expect($reservations)->toHaveCount(1)
        ->and($reservations->first()->state)->toBe(StockReservationState::Hard)
        ->and($reservations->first()->expires_at)->toBeNull()
        ->and($this->service->available($this->company->id, $item->id))->toBe('6.0000');
});

it('reserves only the part of the line a soft hold does not already cover', function (): void {
    $item = Item::factory()->create(['company_id' => $this->company->id]);
    reserve_on_confirm_on_hand($this->company, $item, '10.0000');
    $line = reserve_on_confirm_line($this->order, $item, '5.0000');

    $this->service->reserve(
        $this->company->id,
        $item->id,
        '2.0000',
        StockReservationState::Soft,
        RESERVE_ON_CONFIRM_SOURCE,
        $line->id,
        null,
        now()->addHour(),
    );

    $this->order->update(['status' => SalesOrderStatus::Confirmed]);

    $hard_total = StockReservation::query()
        ->where('source_id', $line->id)
        ->where('state', StockReservationState::Hard->value)
        ->sum('quantity');

    expect((float) $hard_total)->toBe(5.0)
        ->and($this->service->available($this->company->id, $item->id))->toBe('5.0000');
});

it('confirm with expired hold and no stock is rejected', function (): void {
    $item = Item::factory()->create(['company_id' => $this->company->id]);
    reserve_on_confirm_on_hand($this->company, $item, '0.0000');
    $line = reserve_on_confirm_line($this->order, $item, '3.0000');

    StockReservation::factory()->soft()->create([
        'company_id' => $this->company->id,
        'item_id' => $item->id,
        'source_type' => RESERVE_ON_CONFIRM_SOURCE,
        'source_id' => $line->id,
        'quantity' => '3.0000',
        'expires_at' => now()->subMinute(),
    ]);

    expect(fn () => $this->order->update(['status' => SalesOrderStatus::Confirmed]))
        ->toThrow(InsufficientStockException::class);

    expect(StockReservation::query()->where('state', StockReservationState::Hard->value)->count())->toBe(0);
});

it('leaves no partial reservation when a later line cannot be covered', function (): void {
    $stocked = Item::factory()->create(['company_id' => $this->company->id]);
    $empty = Item::factory()->create(['company_id' => $this->company->id]);
    reserve_on_confirm_on_hand($this->company, $stocked, '10.0000');

    reserve_on_confirm_line($this->order, $stocked, '4.0000');
    reserve_on_confirm_line($this->order, $empty, '1.0000');

    expect(fn () => $this->order->update(['status' => SalesOrderStatus::Confirmed]))
        ->toThrow(InsufficientStockException::class);

    expect(StockReservation::query()->count())->toBe(0)
        ->and($this->service->available($this->company->id, $stocked->id))->toBe('10.0000');
});
