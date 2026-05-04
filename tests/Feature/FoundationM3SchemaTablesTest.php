<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates M3 foundation tables for inventory and logistics', function (): void {
    expect(Schema::hasTable('items'))->toBeTrue()
        ->and(Schema::hasTable('warehouses'))->toBeTrue()
        ->and(Schema::hasTable('stock_levels'))->toBeTrue()
        ->and(Schema::hasTable('delivery_notes'))->toBeTrue()
        ->and(Schema::hasTable('purchase_orders'))->toBeTrue()
        ->and(Schema::hasTable('goods_receipts'))->toBeTrue()
        ->and(Schema::hasTable('stock_movements'))->toBeTrue()
        ->and(Schema::hasTable('stock_cost_layers'))->toBeTrue()
        ->and(Schema::hasTable('delivery_note_lines'))->toBeTrue()
        ->and(Schema::hasTable('purchase_order_lines'))->toBeTrue()
        ->and(Schema::hasTable('goods_receipt_lines'))->toBeTrue();
});

it('adds logistics posting timestamps on delivery and receipt headers', function (): void {
    expect(Schema::hasColumns('delivery_notes', ['posted_at', 'inventory_posted_at', 'cogs_journal_entry_id']))->toBeTrue()
        ->and(Schema::hasColumns('goods_receipts', ['posted_at', 'inventory_posted_at']))->toBeTrue();
});
