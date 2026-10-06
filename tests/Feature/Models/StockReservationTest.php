<?php

declare(strict_types=1);

use function Modules\ERP\Helpers\with_company;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\RestrictsCrudWrites;
use Modules\ERP\Enums\StockReservationState;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\StockReservation;
use Modules\ERP\Models\Warehouse;

uses(RefreshDatabase::class);

it('persists the state as an enum and the quantity as decimal:4', function (): void {
    $reservation = StockReservation::factory()->create([
        'state' => StockReservationState::Hard,
        'quantity' => 5,
    ]);

    $fresh = StockReservation::query()->findOrFail($reservation->id);

    expect($fresh->state)->toBe(StockReservationState::Hard)
        ->and($fresh->quantity)->toBe('5.0000')
        ->and($fresh->expires_at)->toBeNull();
});

it('casts expires_at to a datetime when present', function (): void {
    $reservation = StockReservation::factory()->create(['expires_at' => '2026-12-31 10:00:00']);

    expect(StockReservation::query()->findOrFail($reservation->id)->expires_at->toDateTimeString())
        ->toBe('2026-12-31 10:00:00');
});

it('scopes active() to soft and hard reservations only', function (): void {
    $soft = StockReservation::factory()->create(['state' => StockReservationState::Soft]);
    $hard = StockReservation::factory()->create(['state' => StockReservationState::Hard]);
    StockReservation::factory()->create(['state' => StockReservationState::Consumed]);
    StockReservation::factory()->create(['state' => StockReservationState::Released]);

    $active_ids = StockReservation::query()->active()->pluck('id')->all();

    expect($active_ids)->toHaveCount(2)
        ->and($active_ids)->toContain($soft->id, $hard->id);
});

it('scopes queries by the current company', function (): void {
    $first = StockReservation::factory()->create();
    $second = StockReservation::factory()->create();

    $visible_to_first = with_company(
        (int) $first->company_id,
        fn (): array => StockReservation::query()->pluck('id')->all(),
    );

    expect($first->company_id)->not->toBe($second->company_id)
        ->and($visible_to_first)->toBe([$first->id]);
});

it('exposes item and warehouse relations but keeps the source opaque', function (): void {
    $reservation = StockReservation::factory()->create();

    expect($reservation->item())->toBeInstanceOf(BelongsTo::class)
        ->and($reservation->item()->getRelated())->toBeInstanceOf(Item::class)
        ->and($reservation->warehouse())->toBeInstanceOf(BelongsTo::class)
        ->and($reservation->warehouse()->getRelated())->toBeInstanceOf(Warehouse::class)
        ->and(method_exists($reservation, 'source'))->toBeFalse()
        ->and($reservation->source_type)->toBeString()
        ->and($reservation->source_id)->toBeInt();
});

it('allows a reservation without a warehouse', function (): void {
    $reservation = StockReservation::factory()->create(['warehouse_id' => null]);

    expect(StockReservation::query()->findOrFail($reservation->id)->warehouse_id)->toBeNull();
});

it('is write-restricted for generic CRUD', function (): void {
    $reservation = new StockReservation;

    expect($reservation)->toBeInstanceOf(RestrictsCrudWrites::class)
        ->and($reservation->deniedCrudWrites())->toContain('insert', 'update', 'delete');
});

it('keeps the state enum values stable', function (): void {
    expect(StockReservationState::values())->toBe(['soft', 'hard', 'consumed', 'released'])
        ->and(StockReservationState::validationRule())->toBe('in:soft,hard,consumed,released');
});
