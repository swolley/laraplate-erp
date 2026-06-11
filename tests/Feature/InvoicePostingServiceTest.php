<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\MatchStatus;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Models\TaxCode;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Inventory\StockMovementService;

uses(RefreshDatabase::class);

function createInvoicePostingCompany(string $slug): Company
{
    $company = Company::query()->create([
        'slug' => $slug,
        'name' => ucfirst(str_replace('-', ' ', $slug)),
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => (int) now()->format('Y'),
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->endOfYear()->toDateString(),
    ]);

    return $company;
}

it('posts invoice journal, snapshots tax, and updates sales order invoiced quantities', function (): void {
    $company = createInvoicePostingCompany('inv-sale');

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::Confirmed,
    ]);

    $so_line = SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'name' => 'Part',
        'qty_ordered' => 5,
        'qty_delivered' => 0,
        'qty_invoiced' => 0,
        'status' => SalesOrderLineStatus::Open,
    ]);

    $vat = TaxCode::query()->create([
        'company_id' => $company->id,
        'code' => 'VAT22',
        'kind' => 'vat',
        'country' => 'IT',
        'rate' => 22,
        'label' => 'IVA 22%',
        'is_active' => true,
        'effective_from' => now()->toDateString(),
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'sales_order_line_id' => $so_line->id,
        'description' => 'Part',
        'quantity' => 2,
        'unit_price' => 100,
        'tax_code_id' => $vat->id,
    ]);

    $invoice->update(['posted_at' => now()]);
    $invoice->refresh();
    $so_line->refresh();

    expect($invoice->journal_entry_id)->not->toBeNull()
        ->and($invoice->reference)->not->toBeNull()
        ->and($invoice->reference)->toBeString()
        ->and((string) $so_line->qty_invoiced)->toBe('2.0000')
        ->and($so_line->status)->toBe(SalesOrderLineStatus::PartiallyEvased);

    $line = $invoice->lines()->firstOrFail();
    expect($line->tax_code)->toBe('VAT22')
        ->and((string) $line->tax_rate)->toBe('22.0000')
        ->and($line->tax_label)->toBe('IVA 22%');

    $journal = JournalEntry::query()->withoutGlobalScopes()->findOrFail((int) $invoice->journal_entry_id);
    expect($journal->lines)->toHaveCount(3);
});

it('reverses invoice posting and rolls back invoiced quantities when unposted', function (): void {
    $company = createInvoicePostingCompany('inv-unpost');

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::Confirmed,
    ]);

    $so_line = SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'name' => 'Part',
        'qty_ordered' => 3,
        'qty_delivered' => 0,
        'qty_invoiced' => 0,
        'status' => SalesOrderLineStatus::Open,
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'sales_order_line_id' => $so_line->id,
        'description' => 'Part',
        'quantity' => 3,
        'unit_price' => 10,
    ]);

    $invoice->update(['posted_at' => now()]);
    $posted_journal_id = (int) $invoice->fresh()->journal_entry_id;

    $invoice->update(['posted_at' => null]);
    $invoice->refresh();
    $so_line->refresh();

    expect($invoice->journal_entry_id)->toBeNull()
        ->and($invoice->reference)->toBeNull()
        ->and((string) $so_line->qty_invoiced)->toBe('0.0000')
        ->and($so_line->status)->toBe(SalesOrderLineStatus::Open);

    $reversal = JournalEntry::query()->withoutGlobalScopes()
        ->where('reverses_journal_entry_id', $posted_journal_id)
        ->first();
    expect($reversal)->not->toBeNull();
});

it('allocates separate fiscal sequences for sale and purchase invoices with gap_allowed false', function (): void {
    $company = createInvoicePostingCompany('inv-seq');

    $sale = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);
    $sale->lines()->create([
        'line_no' => 1,
        'description' => 'Sale',
        'quantity' => 1,
        'unit_price' => 10,
    ]);

    $purchase = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Purchase,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);
    $purchase->lines()->create([
        'line_no' => 1,
        'description' => 'Purchase',
        'quantity' => 1,
        'unit_price' => 5,
    ]);

    $sale->update(['posted_at' => now()]);
    $purchase->update(['posted_at' => now()]);

    expect($sale->fresh()->reference)->not->toBeNull()
        ->and($purchase->fresh()->reference)->not->toBeNull();

    $sale_row = DocumentSequence::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('document_type', DocumentType::SalesInvoice)
        ->first();
    $purchase_row = DocumentSequence::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('document_type', DocumentType::PurchaseInvoice)
        ->first();

    expect($sale_row)->not->toBeNull()
        ->and($purchase_row)->not->toBeNull()
        ->and((int) $sale_row->last_number)->toBe(1)
        ->and((int) $purchase_row->last_number)->toBe(1)
        ->and($sale_row->gap_allowed)->toBeFalse()
        ->and($purchase_row->gap_allowed)->toBeFalse();
});

it('posts a sale invoice linked to a posted delivery note line', function (): void {
    $company = createInvoicePostingCompany('inv-ddt-ok');

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'WH',
        'code' => 'WH1',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Part',
        'sku' => 'P-DDT',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    app(StockMovementService::class)->recordInbound($company->id, $item->id, $warehouse->id, 50, '1.0000');

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::Confirmed,
    ]);

    $so_line = SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'item_id' => $item->id,
        'name' => 'Part',
        'qty_ordered' => 10,
        'qty_delivered' => 0,
        'qty_invoiced' => 0,
        'status' => SalesOrderLineStatus::Open,
    ]);

    $note = DeliveryNote::query()->create([
        'company_id' => $company->id,
        'sales_order_id' => $order->id,
        'reference' => 'DDT-INV',
    ]);

    $dn_line = DeliveryNoteLine::query()->create([
        'company_id' => $company->id,
        'delivery_note_id' => $note->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'sales_order_line_id' => $so_line->id,
    ]);

    $note->update(['posted_at' => now()]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);

    $invoice_line = $invoice->lines()->create([
        'line_no' => 1,
        'sales_order_line_id' => $so_line->id,
        'description' => 'Part',
        'quantity' => 4,
        'unit_price' => 20,
    ]);

    $invoice_line->delivery_note_lines()->attach($dn_line->id, ['quantity' => 4]);

    $invoice->update(['posted_at' => now()]);

    expect($invoice->fresh()->journal_entry_id)->not->toBeNull()
        ->and($invoice_line->delivery_note_lines()->count())->toBe(1);
});

it('rejects posting when delivery note link exceeds delivered quantity', function (): void {
    $company = Company::query()->create([
        'slug' => 'inv-ddt-bad',
        'name' => 'Inv DDT Bad',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'WH',
        'code' => 'WH2',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Part',
        'sku' => 'P-DDT2',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    app(StockMovementService::class)->recordInbound($company->id, $item->id, $warehouse->id, 20, '1.0000');

    $note = DeliveryNote::query()->create([
        'company_id' => $company->id,
        'reference' => 'DDT-OVER',
    ]);

    $dn_line = DeliveryNoteLine::query()->create([
        'company_id' => $company->id,
        'delivery_note_id' => $note->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
    ]);

    $note->update(['posted_at' => now()]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);

    $invoice_line = $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Part',
        'quantity' => 8,
        'unit_price' => 10,
    ]);

    $invoice_line->delivery_note_lines()->attach($dn_line->id, ['quantity' => 8]);

    expect(fn () => $invoice->update(['posted_at' => now()]))
        ->toThrow(ValidationException::class);
});

it('rejects posting when delivery note is not posted yet', function (): void {
    $company = Company::query()->create([
        'slug' => 'inv-ddt-draft',
        'name' => 'Inv DDT Draft',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'WH',
        'code' => 'WH3',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Part',
        'sku' => 'P-DDT3',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    $note = DeliveryNote::query()->create([
        'company_id' => $company->id,
        'reference' => 'DDT-DRAFT',
    ]);

    $dn_line = DeliveryNoteLine::query()->create([
        'company_id' => $company->id,
        'delivery_note_id' => $note->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 3,
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);

    $invoice_line = $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Part',
        'quantity' => 2,
        'unit_price' => 10,
    ]);

    $invoice_line->delivery_note_lines()->attach($dn_line->id, ['quantity' => 2]);

    expect(fn () => $invoice->update(['posted_at' => now()]))
        ->toThrow(ValidationException::class);
});

it('rejects cumulative invoicing above delivered quantity across posted invoices', function (): void {
    $company = createInvoicePostingCompany('inv-ddt-cum');

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'WH',
        'code' => 'WH4',
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Part',
        'sku' => 'P-DDT4',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    app(StockMovementService::class)->recordInbound($company->id, $item->id, $warehouse->id, 30, '1.0000');

    $note = DeliveryNote::query()->create([
        'company_id' => $company->id,
        'reference' => 'DDT-CUM',
    ]);

    $dn_line = DeliveryNoteLine::query()->create([
        'company_id' => $company->id,
        'delivery_note_id' => $note->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 6,
    ]);

    $note->update(['posted_at' => now()]);

    $first = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);
    $first_line = $first->lines()->create([
        'line_no' => 1,
        'description' => 'First',
        'quantity' => 4,
        'unit_price' => 10,
    ]);
    $first_line->delivery_note_lines()->attach($dn_line->id, ['quantity' => 4]);
    $first->update(['posted_at' => now()]);

    $second = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);
    $second_line = $second->lines()->create([
        'line_no' => 1,
        'description' => 'Second',
        'quantity' => 3,
        'unit_price' => 10,
    ]);
    $second_line->delivery_note_lines()->attach($dn_line->id, ['quantity' => 3]);

    expect(fn () => $second->update(['posted_at' => now()]))
        ->toThrow(ValidationException::class);
});

it('persists matched status when posting a purchase invoice linked to a PO line', function (): void {
    $company = createInvoicePostingCompany('inv-3wm-ok');

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Supplier',
        'is_supplier' => true,
    ]);

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

    $invoice->update(['posted_at' => now()]);

    $invoice_line->refresh();

    expect($invoice->fresh()->journal_entry_id)->not->toBeNull()
        ->and($invoice_line->match_status)->toBe(MatchStatus::Matched)
        ->and($invoice_line->match_discrepancy)->toBeNull();
});

it('allows purchase invoice posting within company-configured three-way tolerance', function (): void {
    $company = createInvoicePostingCompany('inv-3wm-tol');
    $company->settings = [
        'erp' => [
            'three_way_match' => [
                'price_tolerance_percent' => 25,
                'qty_tolerance_percent' => 0,
            ],
        ],
    ];
    $company->save();

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Supplier',
        'is_supplier' => true,
    ]);

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
        'unit_price' => 100.00,
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
        'unit_price' => 120.00,
        'purchase_order_line_id' => $po_line->id,
    ]);

    $invoice->update(['posted_at' => now()]);

    $invoice_line->refresh();

    expect($invoice_line->match_status)->toBe(MatchStatus::Tolerance);
});

it('blocks purchase invoice posting when three-way match exceeds tolerance', function (): void {
    $company = createInvoicePostingCompany('inv-3wm-fail');

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Supplier',
        'is_supplier' => true,
    ]);

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

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Widget',
        'quantity' => 10,
        'unit_price' => 30.00,
        'purchase_order_line_id' => $po_line->id,
    ]);

    expect(fn () => $invoice->update(['posted_at' => now()]))
        ->toThrow(ValidationException::class);
});

it('allows forced three-way match when posting a purchase invoice', function (): void {
    $company = createInvoicePostingCompany('inv-3wm-force');

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Supplier',
        'is_supplier' => true,
    ]);

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

    $invoice->forceThreeWayMatchOnPosting = true;
    $invoice->update(['posted_at' => now()]);

    $invoice_line->refresh();

    expect($invoice_line->match_status)->toBe(MatchStatus::Forced)
        ->and($invoice_line->match_discrepancy)->toHaveKey('po_price');
});
