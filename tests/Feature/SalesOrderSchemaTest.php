<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Casts\QuoteStatus;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Customer;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\Quotation;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Services\SalesOrders\SalesOrderAmendmentService;
use Modules\ERP\Services\Accounting\DocumentNumberAllocator;
use Modules\ERP\Services\SalesOrders\SalesOrderEvasionService;

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

it('allocates sales order document numbers with gap_allowed like quotations', function (): void {
    $company = Company::query()->create([
        'slug' => 'so-seq',
        'name' => 'SO Seq',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $allocator = new DocumentNumberAllocator;

    expect($allocator->next($company, DocumentType::SalesOrder, 0))->toBe('00001')
        ->and($allocator->next($company, DocumentType::SalesOrder, 0))->toBe('00002');

    $row = DocumentSequence::query()->withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('document_type', DocumentType::SalesOrder)
        ->firstOrFail();

    expect((bool) $row->gap_allowed)->toBeTrue();
});

it('rejects a sales order linked to a quotation for another customer', function (): void {
    $company = Company::query()->create([
        'slug' => 'so-val',
        'name' => 'SO Val',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $customer_one = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'One',
    ]);

    $customer_two = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'Two',
    ]);

    $quotation = Quotation::query()->create([
        'company_id' => $company->id,
        'customer_id' => $customer_one->id,
        'currency' => 'EUR',
        'status' => QuoteStatus::DRAFT,
        'version' => 0,
    ]);

    expect(fn (): SalesOrder => SalesOrder::query()->create([
        'company_id' => $company->id,
        'customer_id' => $customer_two->id,
        'quotation_id' => $quotation->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::DRAFT,
    ]))->toThrow(ValidationException::class);
});

it('locks quotation when sales order is confirmed', function (): void {
    $company = Company::query()->create([
        'slug' => 'so-lock-q',
        'name' => 'SO Lock Q',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $customer = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $quotation = Quotation::query()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency' => 'EUR',
        'status' => QuoteStatus::DRAFT,
        'version' => 0,
    ]);

    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'quotation_id' => $quotation->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::DRAFT,
    ]);

    $order->update(['status' => SalesOrderStatus::CONFIRMED]);

    expect($quotation->fresh()?->isLocked())->toBeTrue();
});

it('rejects header field changes on confirmed sales orders', function (): void {
    $company = Company::query()->create([
        'slug' => 'so-lock-h',
        'name' => 'SO Lock H',
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
        'status' => SalesOrderStatus::CONFIRMED,
    ]);

    expect(fn (): bool => $order->update(['currency' => 'USD']))->toThrow(ValidationException::class);
});

it('updates evasion quantities and locks qty_ordered after progress', function (): void {
    $company = Company::query()->create([
        'slug' => 'so-evasion',
        'name' => 'SO Evasion',
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
        'status' => SalesOrderStatus::CONFIRMED,
    ]);

    $line = $order->lines()->create([
        'name' => 'Widget',
        'qty_ordered' => 3,
        'qty_delivered' => 0,
        'qty_invoiced' => 0,
        'status' => SalesOrderLineStatus::OPEN,
    ]);

    $service = new SalesOrderEvasionService;
    $service->registerDelivery($order, [$line->id => 1]);
    $service->registerInvoice($order, [$line->id => 1]);

    $line->refresh();
    $order->refresh();

    expect($line->qty_delivered)->toBe(1)
        ->and($line->qty_invoiced)->toBe(1)
        ->and($line->status)->toBe(SalesOrderLineStatus::PARTIALLY_EVASED)
        ->and($order->status)->toBe(SalesOrderStatus::PARTIALLY_EVASED);

    expect(fn (): bool => $line->update(['qty_ordered' => 5]))->toThrow(ValidationException::class);
});

it('creates a draft amendment from a confirmed order with remaining quantities only', function (): void {
    $company = Company::query()->create([
        'slug' => 'so-amend',
        'name' => 'SO Amend',
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
        'status' => SalesOrderStatus::CONFIRMED,
    ]);

    $line_full = $order->lines()->create([
        'name' => 'Fully delivered',
        'qty_ordered' => 2,
        'qty_delivered' => 2,
        'qty_invoiced' => 2,
        'status' => SalesOrderLineStatus::FULLY_EVASED,
    ]);

    $line_partial = $order->lines()->create([
        'name' => 'Partially delivered',
        'qty_ordered' => 5,
        'qty_delivered' => 2,
        'qty_invoiced' => 1,
        'status' => SalesOrderLineStatus::PARTIALLY_EVASED,
    ]);

    $service = app(SalesOrderAmendmentService::class);
    $amendment = $service->amend($order);

    expect($amendment->amends_sales_order_id)->toBe($order->id)
        ->and($amendment->status)->toBe(SalesOrderStatus::DRAFT)
        ->and($amendment->reference)->not->toBeNull()
        ->and($amendment->lines)->toHaveCount(1)
        ->and($amendment->lines->first()?->name)->toBe($line_partial->name)
        ->and($amendment->lines->first()?->qty_ordered)->toBe(3)
        ->and($amendment->lines->first()?->qty_delivered)->toBe(0)
        ->and($amendment->lines->first()?->qty_invoiced)->toBe(0);

    expect($line_full->fresh()?->qty_ordered)->toBe(2);
});

it('rejects amendment when order is not confirmed or partially evased', function (): void {
    $company = Company::query()->create([
        'slug' => 'so-amend-draft',
        'name' => 'SO Amend Draft',
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
        'qty_ordered' => 1,
        'qty_delivered' => 0,
        'qty_invoiced' => 0,
        'status' => SalesOrderLineStatus::OPEN,
    ]);

    $service = app(SalesOrderAmendmentService::class);

    expect(fn () => $service->amend($order))->toThrow(ValidationException::class);
});
