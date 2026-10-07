<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\SalesOrders\SalesOrderAmendmentService;
use Modules\ERP\Services\SalesOrders\SalesOrderEvasionService;

uses(RefreshDatabase::class);

function supersede_on_hand(Company $company, Item $item, string $quantity): StockLevel
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

function supersede_line(SalesOrder $order, Item $item, string $quantity): SalesOrderLine
{
    return SalesOrderLine::factory()->for($order, 'sales_order')->create([
        'item_id' => $item->id,
        'qty_ordered' => $quantity,
    ]);
}

/**
 * Drives the full supersede flow: amends the (partially evased or) confirmed source and confirms
 * the amendment, which is what marks the source `Amended`.
 */
function supersede_source(SalesOrder $source): void
{
    $amendment = app(SalesOrderAmendmentService::class)->amend($source->fresh());
    $amendment->update(['status' => SalesOrderStatus::Confirmed]);
}

beforeEach(function (): void {
    $this->company = Company::factory()->create();
    $this->item = Item::factory()->create(['company_id' => $this->company->id]);
    supersede_on_hand($this->company, $this->item, '10.0000');
    $this->source = SalesOrder::factory()->create(['company_id' => $this->company->id]);
    $this->line = supersede_line($this->source, $this->item, '4.0000');
    $this->source->update(['status' => SalesOrderStatus::Confirmed]);
});

it('confirming an amendment marks the source order Amended', function (): void {
    supersede_source($this->source);

    expect($this->source->fresh()->status)->toBe(SalesOrderStatus::Amended);
});

it('an Amended order cannot be delivered', function (): void {
    supersede_source($this->source);

    expect(fn (): mixed => app(SalesOrderEvasionService::class)
        ->registerDelivery($this->source->fresh(), [$this->line->id => '1.0000']))
        ->toThrow(ValidationException::class);
});

it('an Amended order cannot be invoiced', function (): void {
    supersede_source($this->source);

    expect(fn (): mixed => app(SalesOrderEvasionService::class)
        ->registerInvoice($this->source->fresh(), [$this->line->id => '1.0000']))
        ->toThrow(ValidationException::class);
});

it('an Amended order cannot be re-amended', function (): void {
    supersede_source($this->source);

    expect(fn (): mixed => app(SalesOrderAmendmentService::class)->amend($this->source->fresh()))
        ->toThrow(ValidationException::class);
});

it('a delivery reversal on an Amended order is still allowed', function (): void {
    // Deliver part of the source first, so there is a movement to reverse, then supersede it.
    app(SalesOrderEvasionService::class)->registerDelivery($this->source->fresh(), [$this->line->id => '2.0000']);
    supersede_source($this->source);

    expect($this->source->fresh()->status)->toBe(SalesOrderStatus::Amended);

    app(SalesOrderEvasionService::class)->unregisterDelivery($this->source->fresh(), [$this->line->id => '2.0000']);

    expect((string) $this->line->fresh()->qty_delivered)->toBe('0.0000');
});

it('a reversal on an Amended order keeps it Amended and the forward guard still blocks', function (): void {
    app(SalesOrderEvasionService::class)->registerDelivery($this->source->fresh(), [$this->line->id => '2.0000']);
    supersede_source($this->source);

    expect($this->source->fresh()->status)->toBe(SalesOrderStatus::Amended);

    // The reversal rewinds the line quantity but must not un-supersede the header.
    app(SalesOrderEvasionService::class)->unregisterDelivery($this->source->fresh(), [$this->line->id => '2.0000']);

    expect($this->source->fresh()->status)->toBe(SalesOrderStatus::Amended)
        ->and((string) $this->line->fresh()->qty_delivered)->toBe('0.0000');

    // The order is still Amended, so a forward delivery is still refused.
    expect(fn (): mixed => app(SalesOrderEvasionService::class)
        ->registerDelivery($this->source->fresh(), [$this->line->id => '1.0000']))
        ->toThrow(ValidationException::class);
});

it('the forward evasion guard message is grammatical for amended and cancelled orders', function (): void {
    supersede_source($this->source);

    try {
        app(SalesOrderEvasionService::class)->registerDelivery($this->source->fresh(), [$this->line->id => '1.0000']);
        $this->fail('Expected a ValidationException for delivering an amended order.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['status'][0])->toBe('An amended sales order cannot be delivered.');
    }

    $cancelled = SalesOrder::factory()->create(['company_id' => $this->company->id]);
    $cancelled_line = supersede_line($cancelled, $this->item, '4.0000');
    $cancelled->update(['status' => SalesOrderStatus::Confirmed]);
    $cancelled->update(['status' => SalesOrderStatus::Cancelled]);

    try {
        app(SalesOrderEvasionService::class)->registerInvoice($cancelled->fresh(), [$cancelled_line->id => '1.0000']);
        $this->fail('Expected a ValidationException for invoicing a cancelled order.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['status'][0])->toBe('A cancelled sales order cannot be invoiced.');
    }
});

it('a cancelled order cannot be delivered', function (): void {
    $this->source->update(['status' => SalesOrderStatus::Cancelled]);

    expect(fn (): mixed => app(SalesOrderEvasionService::class)
        ->registerDelivery($this->source->fresh(), [$this->line->id => '1.0000']))
        ->toThrow(ValidationException::class);
});
