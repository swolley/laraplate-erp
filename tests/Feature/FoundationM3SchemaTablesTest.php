<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\ERP\Enums\ERPTables;

uses(RefreshDatabase::class);

it('creates M3 foundation tables for inventory and logistics', function (): void {
    $schema = DB::connection((string) config('database.default'))->getSchemaBuilder();

    expect($schema->hasTable(ERPTables::Items->value))->toBeTrue()
        ->and($schema->hasTable(ERPTables::Warehouses->value))->toBeTrue()
        ->and($schema->hasTable(ERPTables::StockLevels->value))->toBeTrue()
        ->and($schema->hasTable(ERPTables::DeliveryNotes->value))->toBeTrue()
        ->and($schema->hasTable(ERPTables::PurchaseOrders->value))->toBeTrue()
        ->and($schema->hasTable(ERPTables::GoodsReceipts->value))->toBeTrue()
        ->and($schema->hasTable(ERPTables::StockMovements->value))->toBeTrue()
        ->and($schema->hasTable(ERPTables::StockCostLayers->value))->toBeTrue()
        ->and($schema->hasTable(ERPTables::DeliveryNoteLines->value))->toBeTrue()
        ->and($schema->hasTable(ERPTables::PurchaseOrderLines->value))->toBeTrue()
        ->and($schema->hasTable(ERPTables::GoodsReceiptLines->value))->toBeTrue();
});

it('adds logistics posting timestamps on delivery and receipt headers', function (): void {
    $schema = DB::connection((string) config('database.default'))->getSchemaBuilder();

    expect($schema->hasColumns(ERPTables::DeliveryNotes->value, ['posted_at', 'inventory_posted_at', 'cogs_journal_entry_id']))->toBeTrue()
        ->and($schema->hasColumns(ERPTables::GoodsReceipts->value, ['posted_at', 'inventory_posted_at']))->toBeTrue()
        ->and($schema->hasColumns(ERPTables::Invoices->value, ['posted_at', 'journal_entry_id']))->toBeTrue()
        ->and($schema->hasColumns(ERPTables::InvoiceLines->value, ['sales_order_line_id']))->toBeTrue();
});
