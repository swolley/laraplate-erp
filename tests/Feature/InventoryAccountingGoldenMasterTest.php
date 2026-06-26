<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\ReturnOrder;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\StockMovement;
use Modules\ERP\Models\SupplierReturn;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Inventory\StockMovementService;
use Modules\ERP\Services\Reporting\TrialBalanceService;
use Modules\ERP\Services\Returns\ReturnOrderService;
use Modules\ERP\Services\Returns\SupplierReturnService;

uses(RefreshDatabase::class);

function inventoryGoldenMasterCompany(string $slug): array
{
    $company = Company::query()->create([
        'slug' => $slug . '-' . uniqid(),
        'name' => 'Inventory Golden Master ' . $slug,
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $fiscal_year = FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $period = FiscalPeriod::query()->create([
        'fiscal_year_id' => $fiscal_year->id,
        'period_no' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ]);

    return [$company, $fiscal_year, $period];
}

function inventoryGoldenMasterItemSetup(Company $company, string $slug, string $costing_method = 'weighted_avg'): array
{
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'Main ' . $slug,
        'code' => strtoupper(substr($slug, 0, 8)),
    ]);

    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Inventory Item ' . $slug,
        'sku' => 'IGM-' . strtoupper($slug) . '-' . uniqid(),
        'uom' => 'pcs',
        'costing_method' => $costing_method,
    ]);

    return [$warehouse, $item];
}

function inventoryGoldenMasterSalesOrder(Company $company, Item $item, string $quantity): array
{
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Inventory Customer',
        'is_customer' => true,
    ]);

    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::Confirmed,
    ]);

    $line = SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'item_id' => $item->id,
        'name' => (string) $item->name,
        'qty_ordered' => $quantity,
        'qty_delivered' => '0.0000',
        'qty_invoiced' => '0.0000',
        'status' => SalesOrderLineStatus::Open,
    ]);

    return [$party, $order, $line];
}

function inventoryGoldenMasterDeliveryNote(
    Company $company,
    Warehouse $warehouse,
    Item $item,
    string $quantity,
    ?SalesOrder $sales_order = null,
    ?SalesOrderLine $sales_order_line = null,
    DeliveryNoteDirection $direction = DeliveryNoteDirection::Outbound,
): DeliveryNote {
    $note = DeliveryNote::query()->create([
        'company_id' => $company->id,
        'sales_order_id' => $sales_order?->id,
        'direction' => $direction,
        'reference' => 'IGM-DDT-' . uniqid(),
        'delivered_at' => CarbonImmutable::parse('2026-01-20 09:00:00'),
    ]);

    DeliveryNoteLine::query()->create([
        'company_id' => $company->id,
        'delivery_note_id' => $note->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => $quantity,
        'sales_order_line_id' => $sales_order_line?->id,
    ]);

    return $note;
}

function inventoryGoldenMasterJournalLines(JournalEntry $entry): array
{
    return $entry->lines
        ->mapWithKeys(static fn ($line): array => [
            (string) $line->description => number_format((float) $line->amount_local, 4, '.', ''),
        ])
        ->all();
}

it('posts outbound delivery note stock and COGS into the same accounting period', function (): void {
    [$company] = inventoryGoldenMasterCompany('outbound-cogs');
    [$warehouse, $item] = inventoryGoldenMasterItemSetup($company, 'outbound');

    app(StockMovementService::class)->recordInbound((int) $company->id, (int) $item->id, (int) $warehouse->id, '10.0000', '4.0000');

    [, $sales_order, $sales_order_line] = inventoryGoldenMasterSalesOrder($company, $item, '6.0000');
    $note = inventoryGoldenMasterDeliveryNote($company, $warehouse, $item, '3.0000', $sales_order, $sales_order_line);

    $note->update(['posted_at' => CarbonImmutable::parse('2026-01-20 10:00:00')]);
    $note->refresh();

    $level = StockLevel::query()
        ->where('company_id', (int) $company->id)
        ->where('item_id', (int) $item->id)
        ->where('warehouse_id', (int) $warehouse->id)
        ->firstOrFail();

    $outbound_movement = StockMovement::query()
        ->where('company_id', (int) $company->id)
        ->where('item_id', (int) $item->id)
        ->where('warehouse_id', (int) $warehouse->id)
        ->where('source_type', (new DeliveryNoteLine)->getMorphClass())
        ->where('direction', StockMovementDirection::Out)
        ->firstOrFail();

    $journal = JournalEntry::query()
        ->withoutGlobalScopes()
        ->with('lines')
        ->findOrFail((int) $note->cogs_journal_entry_id);

    $trial_balance = collect(app(TrialBalanceService::class)->generate(
        (int) $company->id,
        CarbonImmutable::parse('2026-01-31 23:59:59'),
    ))->keyBy('account_name');

    expect((string) $level->quantity)->toBe('7.0000')
        ->and((string) $outbound_movement->quantity)->toBe('3.0000')
        ->and((string) $outbound_movement->unit_cost)->toBe('4.0000')
        ->and((string) $sales_order_line->fresh()->qty_delivered)->toBe('3.0000')
        ->and($note->inventory_posted_at)->not->toBeNull()
        ->and($note->cogs_journal_entry_id)->not->toBeNull()
        ->and($journal->posted_at?->format('Y-m-d H:i:s'))->toBe('2026-01-20 10:00:00')
        ->and(inventoryGoldenMasterJournalLines($journal))->toBe([
            'COGS' => '12.0000',
            'Inventory relief' => '-12.0000',
        ])
        ->and($trial_balance->get('Costo delle merci vendute')['debit'])->toBe('12.0000')
        ->and($trial_balance->get('Magazzino merci')['credit'])->toBe('12.0000');
});

it('posts customer return inbound stock at original cost without a new COGS journal', function (): void {
    [$company] = inventoryGoldenMasterCompany('customer-return');
    [$warehouse, $item] = inventoryGoldenMasterItemSetup($company, 'customer-return');

    app(StockMovementService::class)->recordInbound((int) $company->id, (int) $item->id, (int) $warehouse->id, '10.0000', '5.0000');

    [$party, $sales_order, $sales_order_line] = inventoryGoldenMasterSalesOrder($company, $item, '5.0000');
    $outbound_note = inventoryGoldenMasterDeliveryNote($company, $warehouse, $item, '5.0000', $sales_order, $sales_order_line);
    $outbound_note->update(['posted_at' => CarbonImmutable::parse('2026-01-20 10:00:00')]);
    $outbound_note->refresh();

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
        'description' => (string) $item->name,
        'quantity' => '5.0000',
        'unit_price' => '20.0000',
    ]);
    $sales_order_line->qty_invoiced = '5.0000';
    $sales_order_line->save();

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
        'quantity' => '2.0000',
        'unit_cost' => '5.0000',
    ]);

    $processed = app(ReturnOrderService::class)->complete($return_order);
    $processed->refresh();

    $return_delivery_note = $processed->delivery_note()->firstOrFail();
    $return_delivery_note_line = $return_delivery_note->lines()->firstOrFail();
    $inbound_movement = StockMovement::query()
        ->where('company_id', (int) $company->id)
        ->where('source_type', (new DeliveryNoteLine)->getMorphClass())
        ->where('source_id', (int) $return_delivery_note_line->id)
        ->where('direction', StockMovementDirection::In)
        ->firstOrFail();
    $level = StockLevel::query()
        ->where('company_id', (int) $company->id)
        ->where('item_id', (int) $item->id)
        ->where('warehouse_id', (int) $warehouse->id)
        ->firstOrFail();

    expect($return_delivery_note->direction)->toBe(DeliveryNoteDirection::Inbound)
        ->and($return_delivery_note->cogs_journal_entry_id)->toBeNull()
        ->and((string) $inbound_movement->quantity)->toBe('2.0000')
        ->and((string) $inbound_movement->unit_cost)->toBe('5.0000')
        ->and((string) $level->quantity)->toBe('7.0000')
        ->and((string) $invoice_line->fresh()->qty_returned)->toBe('2.0000')
        ->and((string) $sales_order_line->fresh()->qty_returned)->toBe('2.0000')
        ->and(JournalEntry::query()->withoutGlobalScopes()
            ->where('reference_type', (new DeliveryNote)->getMorphClass())
            ->where('reference_id', (int) $return_delivery_note->id)
            ->exists())->toBeFalse()
        ->and($outbound_note->fresh()->cogs_journal_entry_id)->not->toBeNull();
});

it('posts supplier return outbound stock without a sales COGS journal', function (): void {
    [$company] = inventoryGoldenMasterCompany('supplier-return');
    [$warehouse, $item] = inventoryGoldenMasterItemSetup($company, 'supplier-return');

    app(StockMovementService::class)->recordInbound((int) $company->id, (int) $item->id, (int) $warehouse->id, '5.0000', '10.0000');

    $supplier = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Inventory Supplier',
        'is_supplier' => true,
    ]);
    $supplier_return = SupplierReturn::query()->create([
        'company_id' => $company->id,
        'party_id' => $supplier->id,
        'status' => ReturnStatus::Approved,
    ]);
    $supplier_return->lines()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '2.0000',
    ]);

    $processed = app(SupplierReturnService::class)->complete($supplier_return);
    $processed->refresh();

    $delivery_note = $processed->delivery_note()->firstOrFail();
    $delivery_note_line = $delivery_note->lines()->firstOrFail();
    $outbound_movement = StockMovement::query()
        ->where('company_id', (int) $company->id)
        ->where('source_type', (new DeliveryNoteLine)->getMorphClass())
        ->where('source_id', (int) $delivery_note_line->id)
        ->where('direction', StockMovementDirection::Out)
        ->firstOrFail();
    $level = StockLevel::query()
        ->where('company_id', (int) $company->id)
        ->where('item_id', (int) $item->id)
        ->where('warehouse_id', (int) $warehouse->id)
        ->firstOrFail();

    expect($delivery_note->direction)->toBe(DeliveryNoteDirection::Outbound)
        ->and($delivery_note->cogs_journal_entry_id)->toBeNull()
        ->and((string) $outbound_movement->quantity)->toBe('2.0000')
        ->and((string) $outbound_movement->unit_cost)->toBe('10.0000')
        ->and((string) $level->quantity)->toBe('3.0000')
        ->and(JournalEntry::query()->withoutGlobalScopes()
            ->where('reference_type', (new DeliveryNote)->getMorphClass())
            ->where('reference_id', (int) $delivery_note->id)
            ->exists())->toBeFalse();
});

it('reverses COGS journal and restores stock when outbound delivery note is unposted', function (): void {
    [$company] = inventoryGoldenMasterCompany('cogs-reversal');
    [$warehouse, $item] = inventoryGoldenMasterItemSetup($company, 'cogs-reversal');

    app(StockMovementService::class)->recordInbound((int) $company->id, (int) $item->id, (int) $warehouse->id, '8.0000', '6.0000');

    [, $sales_order, $sales_order_line] = inventoryGoldenMasterSalesOrder($company, $item, '4.0000');
    $note = inventoryGoldenMasterDeliveryNote($company, $warehouse, $item, '4.0000', $sales_order, $sales_order_line);
    $note->update(['posted_at' => CarbonImmutable::parse('2026-01-21 10:00:00')]);
    $note->refresh();

    $original_cogs_journal_id = (int) $note->cogs_journal_entry_id;

    $note->update(['posted_at' => null]);
    $note->refresh();

    $level = StockLevel::query()
        ->where('company_id', (int) $company->id)
        ->where('item_id', (int) $item->id)
        ->where('warehouse_id', (int) $warehouse->id)
        ->firstOrFail();
    $reversal = JournalEntry::query()
        ->withoutGlobalScopes()
        ->with('lines')
        ->where('reverses_journal_entry_id', $original_cogs_journal_id)
        ->firstOrFail();

    expect((string) $level->quantity)->toBe('8.0000')
        ->and((string) $sales_order_line->fresh()->qty_delivered)->toBe('0.0000')
        ->and($note->inventory_posted_at)->toBeNull()
        ->and($note->cogs_journal_entry_id)->toBeNull()
        ->and(inventoryGoldenMasterJournalLines($reversal))->toBe([
            'COGS' => '-24.0000',
            'Inventory relief' => '24.0000',
        ]);
});
