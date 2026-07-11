<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DeliveryNoteDirection;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Casts\StockMovementDirection;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\ReturnOrder;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\StockMovement;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Company\ErpCompanySettings;
use Modules\ERP\Services\Returns\ReturnOrderService;

uses(RefreshDatabase::class);

function createReturnOrderFixtures(): array
{
    $company = Company::query()->create([
        'slug' => 'return-order',
        'name' => 'Return Order',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Customer',
        'is_customer' => true,
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'Main',
        'code' => 'MAIN',
    ]);
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Returned item',
        'sku' => 'RET-1',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    return [$company, $party, $warehouse, $item];
}

function createFiscalYearForReturnOrderCompany(Company $company): void
{
    FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => (int) now()->format('Y'),
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->endOfYear()->toDateString(),
    ]);
}

it('approves customer returns from draft', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Draft,
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'unit_cost' => '1.0000',
    ]);

    $approved = app(ReturnOrderService::class)->approve($return_order);

    expect($approved->status)->toBe(ReturnStatus::Approved);
});

it('rejects approval when return party is not a customer', function (): void {
    [$company, , $warehouse, $item] = createReturnOrderFixtures();
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Supplier only',
        'is_customer' => false,
        'is_supplier' => true,
    ]);

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Draft,
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'unit_cost' => '1.0000',
    ]);

    expect(fn () => app(ReturnOrderService::class)->approve($return_order))
        ->toThrow(ValidationException::class);
});

it('records inbound stock when completing approved customer returns', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Approved,
    ]);
    $return_line = $return_order->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 3,
        'unit_cost' => '12.5000',
    ]);

    $processed = app(ReturnOrderService::class)->complete($return_order);

    $level = StockLevel::query()
        ->where('company_id', $company->id)
        ->where('item_id', $item->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();

    expect($processed->status)->toBe(ReturnStatus::Processed)
        ->and($processed->processed_at)->not->toBeNull()
        ->and($processed->delivery_note_id)->not->toBeNull()
        ->and((string) $level->quantity)->toBe('3.0000')
        ->and((float) $level->weighted_avg_cost)->toBe(12.5);

    $delivery_note = $processed->delivery_note()->firstOrFail();
    $delivery_note_line = $delivery_note->lines()->firstOrFail();
    $movement = StockMovement::query()
        ->where('source_type', (new DeliveryNoteLine)->getMorphClass())
        ->where('source_id', $delivery_note_line->id)
        ->firstOrFail();

    expect($delivery_note->direction)->toBe(DeliveryNoteDirection::Inbound)
        ->and($delivery_note->posted_at)->not->toBeNull()
        ->and($delivery_note->inventory_posted_at)->not->toBeNull()
        ->and($return_line->fresh()->delivery_note_line_id)->toBe($delivery_note_line->id)
        ->and($movement->direction)->toBe(StockMovementDirection::In);
});

it('tracks returned quantities on customer source lines', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();

    $sales_order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::Confirmed,
    ]);
    $sales_order_line = SalesOrderLine::query()->create([
        'sales_order_id' => $sales_order->id,
        'item_id' => $item->id,
        'name' => 'Returned item',
        'qty_ordered' => 5,
        'qty_delivered' => 5,
        'qty_invoiced' => 5,
        'status' => SalesOrderLineStatus::FullyEvased,
    ]);
    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $invoice_line = $invoice->lines()->create([
        'line_no' => 1,
        'sales_order_line_id' => $sales_order_line->id,
        'description' => 'Returned item',
        'quantity' => 5,
        'unit_price' => 10,
    ]);
    $source_delivery_note = DeliveryNote::query()->create([
        'company_id' => $company->id,
        'direction' => DeliveryNoteDirection::Outbound,
        'reference' => 'DN-ORIGINAL',
        'delivered_at' => now(),
    ]);
    $source_delivery_note_line = $source_delivery_note->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'sales_order_line_id' => $sales_order_line->id,
    ]);
    StockMovement::query()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'direction' => StockMovementDirection::Out,
        'quantity' => 5,
        'unit_cost' => '12.5000',
        'source_type' => (new DeliveryNoteLine)->getMorphClass(),
        'source_id' => $source_delivery_note_line->id,
    ]);
    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'invoice_id' => $invoice->id,
        'status' => ReturnStatus::Approved,
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'invoice_line_id' => $invoice_line->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 2,
        'unit_cost' => '12.5000',
    ]);

    app(ReturnOrderService::class)->complete($return_order);

    expect((string) $invoice_line->fresh()->qty_returned)->toBe('2.0000')
        ->and((string) $sales_order_line->fresh()->qty_returned)->toBe('2.0000');
});

it('rejects completing customer returns before approval', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Draft,
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'unit_cost' => '1.0000',
    ]);

    expect(fn () => app(ReturnOrderService::class)->complete($return_order))
        ->toThrow(ValidationException::class);
});

it('prevents completing the same customer return twice', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Approved,
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'unit_cost' => '1.0000',
    ]);

    $service = app(ReturnOrderService::class);
    $service->complete($return_order);

    expect(fn () => $service->complete($return_order->fresh()))
        ->toThrow(ValidationException::class);
});

it('creates a manual credit note for processed customer returns from the source invoice', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();
    createFiscalYearForReturnOrderCompany($company);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $returned_invoice_line = $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Returned item',
        'quantity' => 5,
        'unit_price' => 10,
    ]);
    $invoice->lines()->create([
        'line_no' => 2,
        'description' => 'Kept item',
        'quantity' => 3,
        'unit_price' => 15,
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
        'invoice_line_id' => $returned_invoice_line->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 2,
        'unit_cost' => '12.5000',
    ]);

    $credit_note = app(ReturnOrderService::class)->createCreditNote($return_order);

    expect($credit_note->invoice_type)->toBe(InvoiceType::CreditNote)
        ->and($credit_note->direction)->toBe(InvoiceDirection::Sale)
        ->and((int) $credit_note->credited_invoice_id)->toBe((int) $invoice->id)
        ->and((int) $credit_note->party_id)->toBe((int) $party->id)
        ->and((int) $return_order->fresh()->credit_note_invoice_id)->toBe((int) $credit_note->id)
        ->and($credit_note->lines()->count())->toBe(1)
        ->and((string) $credit_note->lines()->firstOrFail()->quantity)->toBe('2.0000');
});

it('prevents creating duplicate customer return credit notes', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();
    createFiscalYearForReturnOrderCompany($company);

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
        'unit_price' => 10,
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
        'quantity' => 2,
        'unit_cost' => '12.5000',
    ]);

    $service = app(ReturnOrderService::class);
    $service->createCreditNote($return_order);

    expect(fn () => $service->createCreditNote($return_order->fresh()))
        ->toThrow(ValidationException::class);
});

it('automatically creates a credit note when completing customer returns if enabled', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();
    createFiscalYearForReturnOrderCompany($company);

    $company->settings = [
        'erp' => [
            'returns' => [
                'auto_create_notes_on_complete' => true,
            ],
        ],
    ];
    $company->save();

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
        'status' => ReturnStatus::Approved,
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'invoice_line_id' => $invoice_line->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 2,
        'unit_cost' => '12.5000',
        'unit_price' => '7.5000',
    ]);

    $processed = app(ReturnOrderService::class)->complete($return_order);
    $credit_note = Invoice::query()->findOrFail((int) $processed->fresh()->credit_note_invoice_id);

    expect($processed->status)->toBe(ReturnStatus::Processed)
        ->and($credit_note->invoice_type)->toBe(InvoiceType::CreditNote)
        ->and($credit_note->direction)->toBe(InvoiceDirection::Sale)
        ->and((int) $credit_note->credited_invoice_id)->toBe((int) $invoice->id)
        ->and((string) $credit_note->lines()->firstOrFail()->quantity)->toBe('2.0000')
        ->and((string) $credit_note->lines()->firstOrFail()->unit_price)->toBe('7.5000');
});

it('does not automatically create a credit note when completing customer returns by default', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();

    expect(app(ErpCompanySettings::class)->autoCreateNotesOnComplete($company))->toBeFalse();

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Approved,
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'unit_cost' => '1.0000',
    ]);

    $processed = app(ReturnOrderService::class)->complete($return_order);

    expect($processed->status)->toBe(ReturnStatus::Processed)
        ->and($processed->fresh()->credit_note_invoice_id)->toBeNull();
});

it('cancels draft customer returns', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Draft,
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'unit_cost' => '1.0000',
    ]);

    $cancelled = app(ReturnOrderService::class)->cancel($return_order);

    expect($cancelled->status)->toBe(ReturnStatus::Cancelled);
});

it('rejects cancelling processed customer returns', function (): void {
    [$company, $party, $warehouse, $item] = createReturnOrderFixtures();

    $return_order = ReturnOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'status' => ReturnStatus::Processed,
        'processed_at' => now(),
    ]);
    $return_order->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'unit_cost' => '1.0000',
    ]);

    expect(fn () => app(ReturnOrderService::class)->cancel($return_order))
        ->toThrow(ValidationException::class);
});
