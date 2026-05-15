<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Enums\ERPTables;

uses(RefreshDatabase::class);

it('creates M3 foundation tables for inventory and logistics', function (): void {
    expect(Schema::hasTable(ERPTables::Items->value))->toBeTrue()
        ->and(Schema::hasTable(ERPTables::Warehouses->value))->toBeTrue()
        ->and(Schema::hasTable(ERPTables::StockLevels->value))->toBeTrue()
        ->and(Schema::hasTable(ERPTables::DeliveryNotes->value))->toBeTrue()
        ->and(Schema::hasTable(ERPTables::PurchaseOrders->value))->toBeTrue()
        ->and(Schema::hasTable(ERPTables::GoodsReceipts->value))->toBeTrue()
        ->and(Schema::hasTable(ERPTables::StockMovements->value))->toBeTrue()
        ->and(Schema::hasTable(ERPTables::StockCostLayers->value))->toBeTrue()
        ->and(Schema::hasTable(ERPTables::DeliveryNoteLines->value))->toBeTrue()
        ->and(Schema::hasTable(ERPTables::PurchaseOrderLines->value))->toBeTrue()
        ->and(Schema::hasTable(ERPTables::GoodsReceiptLines->value))->toBeTrue();
});

it('adds logistics posting timestamps on delivery and receipt headers', function (): void {
    expect(Schema::hasColumns(ERPTables::DeliveryNotes->value, ['posted_at', 'inventory_posted_at', 'cogs_journal_entry_id']))->toBeTrue()
        ->and(Schema::hasColumns(ERPTables::GoodsReceipts->value, ['posted_at', 'inventory_posted_at']))->toBeTrue()
        ->and(Schema::hasColumns(ERPTables::Invoices->value, ['posted_at', 'journal_entry_id']))->toBeTrue()
        ->and(Schema::hasColumns(ERPTables::InvoiceLines->value, ['sales_order_line_id']))->toBeTrue();
});
