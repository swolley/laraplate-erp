<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Events\SalesOrderConfirmed;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\SalesOrder;

uses(RefreshDatabase::class);

/**
 * @return array{company: Company, party: Party}
 */
function makeCustomerContext(): array
{
    $company = Company::query()->create([
        'slug' => 'so-confirmed',
        'name' => 'So Confirmed',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
        'is_customer' => true,
    ]);

    return ['company' => $company, 'party' => $party];
}

it('dispatches SalesOrderConfirmed once when an order transitions to confirmed', function (): void {
    Event::fake([SalesOrderConfirmed::class]);
    ['company' => $company, 'party' => $party] = makeCustomerContext();

    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::Draft,
    ]);

    Event::assertNotDispatched(SalesOrderConfirmed::class);

    $order->update(['status' => SalesOrderStatus::Confirmed]);

    Event::assertDispatchedTimes(SalesOrderConfirmed::class, 1);
    Event::assertDispatched(
        SalesOrderConfirmed::class,
        static fn (SalesOrderConfirmed $event): bool => $event->salesOrder->is($order),
    );
});

it('does not re-dispatch on a save that leaves the status unchanged', function (): void {
    Event::fake([SalesOrderConfirmed::class]);
    ['company' => $company, 'party' => $party] = makeCustomerContext();

    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::Confirmed,
    ]);

    Event::assertDispatchedTimes(SalesOrderConfirmed::class, 1);

    $order->update(['notes' => 'unrelated change']);

    Event::assertDispatchedTimes(SalesOrderConfirmed::class, 1);
});
