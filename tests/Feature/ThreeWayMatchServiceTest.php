<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\MatchStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\GoodsReceipt;
use Modules\ERP\Models\GoodsReceiptLine;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Models\Warehouse;
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
        'invoice_type' => InvoiceType::Invoice->value,
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
        'invoice_type' => InvoiceType::Invoice->value,
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
        ->toThrow(Illuminate\Validation\ValidationException::class);
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
        'invoice_type' => InvoiceType::Invoice->value,
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

it('flags a goods-receipt quantity discrepancy beyond tolerance', function (): void {
    $company = Company::query()->create([
        'slug' => '3wm-gr',
        'name' => '3WM GR',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $warehouse = Warehouse::query()->create(['company_id' => $company->id, 'name' => 'WH', 'code' => 'GR-WH']);
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Bolt',
        'sku' => 'B-3WM',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);
    $receipt = GoodsReceipt::query()->create([
        'company_id' => $company->id,
        'reference' => 'GR-3WM',
    ]);
    $gr_line = GoodsReceiptLine::query()->create([
        'company_id' => $company->id,
        'goods_receipt_id' => $receipt->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'unit_cost' => '3.5000',
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Purchase,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);
    $invoice_line = $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Bolt',
        'quantity' => 12,
        'unit_price' => 3.5,
        'goods_receipt_line_id' => $gr_line->id,
    ]);

    $service = app(ThreeWayMatchService::class);

    expect(fn () => $service->validate($invoice_line, qty_tolerance_percent: 0.0))
        ->toThrow(Illuminate\Validation\ValidationException::class);

    $result = $service->validate($invoice_line, qty_tolerance_percent: 0.0, force: true);
    expect($result['status'])->toBe(MatchStatus::Forced)
        ->and($result['discrepancies'])->toHaveKey('gr_qty');
});

it('returns unmatched when the invoice line has no PO or GR link', function (): void {
    $company = Company::query()->create([
        'slug' => '3wm-unmatched',
        'name' => '3WM Unmatched',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Purchase,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);
    $invoice_line = $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Loose item',
        'quantity' => 1,
        'unit_price' => 10,
    ]);

    $result = app(ThreeWayMatchService::class)->validate($invoice_line);

    expect($result['status'])->toBe(MatchStatus::Unmatched)
        ->and($result['discrepancies'])->toHaveKey('reason');
});
