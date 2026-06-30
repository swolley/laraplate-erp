<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\QuotationItem;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;

uses(RefreshDatabase::class);

it('defines quotation item relationship', function (): void {
    $relation = (new SalesOrderLine)->quotation_item();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(QuotationItem::class);
});

it('rejects changing qty_ordered after delivery has started', function (): void {
    $company = Company::query()->create([
        'slug' => 'so-line-' . uniqid(),
        'name' => 'SO Line Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
        'is_customer' => true,
        'is_supplier' => false,
    ]);
    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::Confirmed,
    ]);
    $line = SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'name' => 'Widget',
        'qty_ordered' => '5.0000',
        'qty_delivered' => '1.0000',
        'qty_invoiced' => '0.0000',
        'status' => SalesOrderLineStatus::Open,
    ]);

    expect(fn () => $line->update(['qty_ordered' => '6.0000']))
        ->toThrow(ValidationException::class);
});
