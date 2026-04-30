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
        ->and(Schema::hasTable('goods_receipts'))->toBeTrue();
});
