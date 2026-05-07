<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\MatchStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Services\Purchasing\ThreeWayMatchService;

uses(RefreshDatabase::class);

it('returns matched when invoice line matches PO line exactly', function (): void {
    $company = Company::query()->create([
        'slug' => '3wm-exact',
        'name' => '3WM Exact',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $party = Party::query()->create(['company_id' => $company->id, 'name' => 'Supplier', 'is_supplier' => true]);

    $po = PurchaseOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => 'draft',
    ]);

    $po_line = PurchaseOrderLine::query()->create([
        'purchase_order_id' => $po->id,
        'name' => 'Widget',
        'qty_ordered' => 10,
        'qty_received' => 0,
        'unit_price' => 25.00,
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Purchase,
        'currency' => 'EUR',
    ]);

    $invoice_line = $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Widget',
        'quantity' => 10,
        'unit_price' => 25.00,
        'purchase_order_line_id' => $po_line->id,
    ]);

    $service = app(ThreeWayMatchService::class);
    $result = $service->validate($invoice_line);

    expect($result['status'])->toBe(MatchStatus::Matched)
        ->and($result['discrepancies'])->toBeEmpty();
});

it('throws validation exception when price exceeds tolerance', function (): void {
    $company = Company::query()->create([
        'slug' => '3wm-fail',
        'name' => '3WM Fail',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $party = Party::query()->create(['company_id' => $company->id, 'name' => 'Supplier', 'is_supplier' => true]);

    $po = PurchaseOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => 'draft',
    ]);

    $po_line = PurchaseOrderLine::query()->create([
        'purchase_order_id' => $po->id,
        'name' => 'Widget',
        'qty_ordered' => 10,
        'qty_received' => 0,
        'unit_price' => 25.00,
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Purchase,
        'currency' => 'EUR',
    ]);

    $invoice_line = $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Widget',
        'quantity' => 10,
        'unit_price' => 30.00,
        'purchase_order_line_id' => $po_line->id,
    ]);

    $service = app(ThreeWayMatchService::class);

    expect(fn () => $service->validate($invoice_line, price_tolerance_percent: 0.0))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('returns forced when force flag overrides breach', function (): void {
    $company = Company::query()->create([
        'slug' => '3wm-force',
        'name' => '3WM Force',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $party = Party::query()->create(['company_id' => $company->id, 'name' => 'Supplier', 'is_supplier' => true]);

    $po = PurchaseOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => 'draft',
    ]);

    $po_line = PurchaseOrderLine::query()->create([
        'purchase_order_id' => $po->id,
        'name' => 'Widget',
        'qty_ordered' => 10,
        'qty_received' => 0,
        'unit_price' => 25.00,
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Purchase,
        'currency' => 'EUR',
    ]);

    $invoice_line = $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Widget',
        'quantity' => 10,
        'unit_price' => 30.00,
        'purchase_order_line_id' => $po_line->id,
    ]);

    $service = app(ThreeWayMatchService::class);
    $result = $service->validate($invoice_line, price_tolerance_percent: 0.0, force: true);

    expect($result['status'])->toBe(MatchStatus::Forced)
        ->and($result['discrepancies'])->toHaveKey('po_price');
});
