<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Models\ReturnOrder;
use Modules\ERP\Models\SupplierReturn;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Returns\ReturnOrderService;
use Modules\ERP\Services\Returns\SupplierReturnService;

uses(RefreshDatabase::class);

function createReturnLineOverrideCompany(string $slug, bool $supplier = false): array
{
    $company = Company::query()->create([
        'slug' => $slug,
        'name' => 'Return Override',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => (int) now()->format('Y'),
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->endOfYear()->toDateString(),
    ]);

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => $supplier ? 'Supplier' : 'Customer',
        'is_customer' => ! $supplier,
        'is_supplier' => $supplier,
    ]);

    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'Main',
        'code' => strtoupper(substr($slug, 0, 4)),
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Returned item',
        'sku' => strtoupper($slug),
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    return [$company, $party, $warehouse, $item];
}

it('uses customer return line unit price overrides when creating credit notes', function (): void {
    [$company, $party, $warehouse, $item] = createReturnLineOverrideCompany('return-credit');

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $invoice_line = $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Returned item',
        'quantity' => 5,
        'unit_price' => '10.0000',
    ]);
    $invoice->update(['posted_at' => now()]);

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'invoice_id' => $invoice->id,
        'status' => ReturnStatus::Processed,
        'processed_at' => now(),
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'invoice_line_id' => $invoice_line->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '2.0000',
        'unit_cost' => '1.0000',
        'unit_price' => '7.5000',
    ]);

    $credit_note = app(ReturnOrderService::class)->createCreditNote($return_order);

    expect((string) $credit_note->lines()->firstOrFail()->quantity)->toBe('2.0000')
        ->and((string) $credit_note->lines()->firstOrFail()->unit_price)->toBe('7.5000');
});

it('uses source purchase invoice line prices when creating supplier debit notes', function (): void {
    [$company, $party, $warehouse, $item] = createReturnLineOverrideCompany('return-debit', supplier: true);

    $purchase_order = PurchaseOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => 'received',
    ]);
    $purchase_order_line = PurchaseOrderLine::query()->create([
        'purchase_order_id' => $purchase_order->id,
        'item_id' => $item->id,
        'name' => 'Returned supplier item',
        'qty_ordered' => 5,
        'qty_received' => 5,
        'unit_price' => '11.5000',
    ]);
    $purchase_invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Purchase,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $purchase_invoice_line = $purchase_invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Returned supplier item',
        'quantity' => 5,
        'unit_price' => '13.7500',
        'purchase_order_line_id' => $purchase_order_line->id,
    ]);

    $supplier_return = SupplierReturn::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'purchase_order_id' => $purchase_order->id,
        'status' => ReturnStatus::Processed,
        'processed_at' => now(),
    ]);
    $supplier_return->lines()->create([
        'company_id' => $company->id,
        'purchase_order_line_id' => $purchase_order_line->id,
        'invoice_line_id' => $purchase_invoice_line->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '2.0000',
    ]);

    $debit_note = app(SupplierReturnService::class)->createDebitNote($supplier_return);

    expect((string) $debit_note->lines()->firstOrFail()->quantity)->toBe('2.0000')
        ->and((int) $debit_note->credited_invoice_id)->toBe((int) $purchase_invoice->id)
        ->and((string) $debit_note->lines()->firstOrFail()->unit_price)->toBe('13.7500');
});
