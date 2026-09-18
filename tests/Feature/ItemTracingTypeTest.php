<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\TracingType;
use Modules\ERP\Models\Item;

uses(RefreshDatabase::class);

// tracing_type is an intrinsic Item attribute owned by ERP: it governs how the
// item is traced across purchase, warehouse and sales flows, with no MES module
// involved. These tests prove the column and its cast exist on the ERP schema on
// their own.

it('defaults tracing_type to None when unspecified', function (): void {
    $item = Item::factory()->create();

    expect($item->refresh()->tracing_type)->toBe(TracingType::None);
});

it('persists and retrieves each TracingType on an Item', function (): void {
    foreach (TracingType::cases() as $type) {
        $item = Item::factory()->create(['tracing_type' => $type->value]);

        expect(Item::query()->findOrFail($item->id)->tracing_type)
            ->toBeInstanceOf(TracingType::class)
            ->toBe($type);
    }
});
