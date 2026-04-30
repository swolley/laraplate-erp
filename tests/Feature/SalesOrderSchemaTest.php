<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Customer;
use Modules\ERP\Models\SalesOrder;

uses(RefreshDatabase::class);

it('creates sales order tables', function (): void {
    expect(Schema::hasTable('sales_orders'))->toBeTrue()
        ->and(Schema::hasTable('sales_order_lines'))->toBeTrue();
});

it('persists a sales order with lines', function (): void {
    $company = Company::query()->create([
        'slug' => 'so-co',
        'name' => 'SO Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $customer = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::DRAFT,
    ]);

    $order->lines()->create([
        'name' => 'Widget',
        'qty_ordered' => 2,
        'qty_delivered' => 0,
        'qty_invoiced' => 0,
        'status' => SalesOrderLineStatus::OPEN,
    ]);

    expect($order->lines)->toHaveCount(1)
        ->and($order->status)->toBe(SalesOrderStatus::DRAFT);
});
