<?php

declare(strict_types=1);

/**
 * The invariant these tests exist for: a factory never writes a document number.
 *
 * `reference` is allocated by `DocumentNumberAllocator` — at posting time for invoices, at the
 * creation page for orders. A factory that filled it in would make every numbering test pass for
 * the wrong reason, and the gap would only surface in production, where the sequence is real.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\DeliveryNoteDirection;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;

uses(RefreshDatabase::class);

it('creates every document header', function (): void {
    expect(SalesOrder::factory()->create()->exists)->toBeTrue()
        ->and(PurchaseOrder::factory()->create()->exists)->toBeTrue()
        ->and(DeliveryNote::factory()->create()->exists)->toBeTrue()
        ->and(Invoice::factory()->create()->exists)->toBeTrue();
});

it('leaves the document number to the allocator', function (): void {
    expect(Invoice::factory()->create()->reference)->toBeNull()
        ->and(SalesOrder::factory()->create()->reference)->toBeNull()
        ->and(PurchaseOrder::factory()->create()->reference)->toBeNull()
        ->and(DeliveryNote::factory()->create()->reference)->toBeNull();
});

it('accepts a fixed reference only when a test asks for one', function (): void {
    expect(Invoice::factory()->numbered('INV-2026-0001')->create()->reference)->toBe('INV-2026-0001');
});

it('builds headers with their lines', function (): void {
    $order = SalesOrder::factory()->withLines(3)->create();
    $purchase = PurchaseOrder::factory()->withLines()->create();
    $invoice = Invoice::factory()->withLines(2)->create();

    expect($order->lines()->count())->toBe(3)
        ->and($purchase->lines()->count())->toBe(2)
        ->and($invoice->lines()->count())->toBe(2);
});

it('numbers invoice lines from one, in order', function (): void {
    $invoice = Invoice::factory()->withLines(3)->create();

    expect($invoice->lines()->orderBy('line_no')->pluck('line_no')->all())->toBe([1, 2, 3]);
});

it('keeps a whole document chain inside one company', function (): void {
    $company = Company::factory()->create();

    $invoice = Invoice::factory()->for($company)->withLines()->create();
    $order = SalesOrder::factory()->for($company)->withLines()->create();

    expect(Company::query()->count())->toBe(1)
        ->and($invoice->company_id)->toBe($company->id)
        ->and($order->company_id)->toBe($company->id);
});

it('defaults to the sale direction and the plain invoice type', function (): void {
    $invoice = Invoice::factory()->create();

    expect($invoice->direction)->toBe(InvoiceDirection::Sale)
        ->and($invoice->invoice_type)->toBe(InvoiceType::Invoice);

    expect(Invoice::factory()->purchase()->create()->direction)->toBe(InvoiceDirection::Purchase)
        ->and(Invoice::factory()->creditNote()->create()->invoice_type)->toBe(InvoiceType::CreditNote);
});

it('starts orders as drafts and delivery notes outbound', function (): void {
    expect(SalesOrder::factory()->create()->status)->toBe(SalesOrderStatus::Draft)
        ->and(SalesOrder::factory()->confirmed()->create()->status)->toBe(SalesOrderStatus::Confirmed)
        ->and(DeliveryNote::factory()->create()->direction)->toBe(DeliveryNoteDirection::Outbound)
        ->and(DeliveryNote::factory()->inbound()->create()->direction)->toBe(DeliveryNoteDirection::Inbound);
});

it('creates lines standing alone too', function (): void {
    expect(SalesOrderLine::factory()->create()->exists)->toBeTrue()
        ->and(PurchaseOrderLine::factory()->create()->exists)->toBeTrue()
        ->and(InvoiceLine::factory()->create()->exists)->toBeTrue();
});
