<?php

declare(strict_types=1);

use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Enums\StockReservationState;
use Modules\ERP\Exceptions\InsufficientStockException;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\StockReservation;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Inventory\StockReservationService;

uses(RefreshDatabase::class);

/**
 * Seeds one on-hand row for the item in a fresh warehouse of the same company.
 */
function reservation_test_on_hand(Company $company, Item $item, string $quantity): StockLevel
{
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

    return StockLevel::query()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => $quantity,
        'weighted_avg_cost' => '1.0000',
    ]);
}

beforeEach(function (): void {
    $this->company = Company::factory()->create();
    $this->item = Item::factory()->create(['company_id' => $this->company->id]);
    $this->service = app(StockReservationService::class);
});

it('available equals on_hand minus active reservations', function (): void {
    reservation_test_on_hand($this->company, $this->item, '4.0000');
    reservation_test_on_hand($this->company, $this->item, '6.0000');

    expect($this->service->available($this->company->id, $this->item->id))->toBe('10.0000');

    StockReservation::factory()->hard()->create([
        'company_id' => $this->company->id,
        'item_id' => $this->item->id,
        'quantity' => '3.0000',
    ]);
    StockReservation::factory()->consumed()->create([
        'company_id' => $this->company->id,
        'item_id' => $this->item->id,
        'quantity' => '2.0000',
    ]);
    StockReservation::factory()->released()->create([
        'company_id' => $this->company->id,
        'item_id' => $this->item->id,
        'quantity' => '5.0000',
    ]);

    expect($this->service->available($this->company->id, $this->item->id))->toBe('7.0000');
});

it('available is scoped to the company and the item', function (): void {
    reservation_test_on_hand($this->company, $this->item, '10.0000');

    $other_item = Item::factory()->create(['company_id' => $this->company->id]);
    reservation_test_on_hand($this->company, $other_item, '50.0000');
    StockReservation::factory()->hard()->create([
        'company_id' => $this->company->id,
        'item_id' => $other_item->id,
        'quantity' => '9.0000',
    ]);

    expect($this->service->available($this->company->id, $this->item->id))->toBe('10.0000')
        ->and($this->service->available($this->company->id, $other_item->id))->toBe('41.0000');
});

it('available ignores expired soft reservations', function (): void {
    reservation_test_on_hand($this->company, $this->item, '10.0000');

    StockReservation::factory()->soft()->create([
        'company_id' => $this->company->id,
        'item_id' => $this->item->id,
        'quantity' => '4.0000',
        'expires_at' => now()->subMinute(),
    ]);

    expect($this->service->available($this->company->id, $this->item->id))->toBe('10.0000');

    StockReservation::factory()->soft()->create([
        'company_id' => $this->company->id,
        'item_id' => $this->item->id,
        'quantity' => '1.0000',
        'expires_at' => now()->addHour(),
    ]);
    StockReservation::factory()->soft()->create([
        'company_id' => $this->company->id,
        'item_id' => $this->item->id,
        'quantity' => '2.0000',
        'expires_at' => null,
    ]);

    expect($this->service->available($this->company->id, $this->item->id))->toBe('7.0000');
});

it('reserve persists a soft reservation with its expiry and lowers availability', function (): void {
    reservation_test_on_hand($this->company, $this->item, '10.0000');
    $expires_at = now()->addMinutes(15);

    $reservation = $this->service->reserve(
        $this->company->id,
        $this->item->id,
        '2.5',
        StockReservationState::Soft,
        'external_document',
        77,
        expiresAt: $expires_at,
    );

    $fresh = StockReservation::query()->findOrFail($reservation->id);

    expect($fresh->state)->toBe(StockReservationState::Soft)
        ->and($fresh->quantity)->toBe('2.5000')
        ->and($fresh->company_id)->toBe($this->company->id)
        ->and($fresh->item_id)->toBe($this->item->id)
        ->and($fresh->warehouse_id)->toBeNull()
        ->and($fresh->source_type)->toBe('external_document')
        ->and($fresh->source_id)->toBe(77)
        ->and($fresh->expires_at->timestamp)->toBe($expires_at->timestamp)
        ->and($this->service->available($this->company->id, $this->item->id))->toBe('7.5000');
});

it('reserve stores a hard reservation without an expiry and can pin a warehouse', function (): void {
    $level = reservation_test_on_hand($this->company, $this->item, '10.0000');

    $reservation = $this->service->reserve(
        $this->company->id,
        $this->item->id,
        '4.0000',
        StockReservationState::Hard,
        'external_document',
        78,
        warehouseId: $level->warehouse_id,
        expiresAt: now()->addHour(),
    );

    $fresh = StockReservation::query()->findOrFail($reservation->id);

    expect($fresh->state)->toBe(StockReservationState::Hard)
        ->and($fresh->expires_at)->toBeNull()
        ->and($fresh->warehouse_id)->toBe($level->warehouse_id)
        ->and($this->service->available($this->company->id, $this->item->id))->toBe('6.0000');
});

it('reserve rejects when requested exceeds available', function (): void {
    reservation_test_on_hand($this->company, $this->item, '2.0000');

    expect(fn () => $this->service->reserve(
        $this->company->id,
        $this->item->id,
        '2.0001',
        StockReservationState::Hard,
        'external_document',
        1,
    ))->toThrow(InsufficientStockException::class);

    expect(StockReservation::query()->count())->toBe(0)
        ->and($this->service->available($this->company->id, $this->item->id))->toBe('2.0000');
});

it('reserve can take exactly the available quantity', function (): void {
    reservation_test_on_hand($this->company, $this->item, '2.0000');

    $this->service->reserve($this->company->id, $this->item->id, '2.0000', StockReservationState::Hard, 'external_document', 1);

    expect($this->service->available($this->company->id, $this->item->id))->toBe('0.0000');
});

it('reserve rejects a second concurrent reservation beyond on_hand', function (): void {
    reservation_test_on_hand($this->company, $this->item, '1.0000');

    $this->service->reserve($this->company->id, $this->item->id, '1.0000', StockReservationState::Hard, 'external_document', 1);

    expect(fn () => $this->service->reserve(
        $this->company->id,
        $this->item->id,
        '1.0000',
        StockReservationState::Hard,
        'external_document',
        2,
    ))->toThrow(InsufficientStockException::class);

    expect(StockReservation::query()->count())->toBe(1);
});

it('reserve rejects an insufficient soft hold only against live reservations', function (): void {
    reservation_test_on_hand($this->company, $this->item, '1.0000');
    StockReservation::factory()->soft()->create([
        'company_id' => $this->company->id,
        'item_id' => $this->item->id,
        'quantity' => '1.0000',
        'expires_at' => now()->subSecond(),
    ]);

    $reservation = $this->service->reserve(
        $this->company->id,
        $this->item->id,
        '1.0000',
        StockReservationState::Hard,
        'external_document',
        9,
    );

    expect($reservation->exists)->toBeTrue();
});

it('reserve serializes on the per-item atomic lock and frees it afterwards', function (): void {
    reservation_test_on_hand($this->company, $this->item, '5.0000');
    $key = "erp:stock-reservation:{$this->company->id}:{$this->item->id}";

    /** @var Lock $held */
    $held = Cache::lock($key, 60);
    expect($held->get())->toBeTrue();

    Sleep::fake(syncWithCarbon: true);

    expect(fn () => $this->service->reserve(
        $this->company->id,
        $this->item->id,
        '1.0000',
        StockReservationState::Hard,
        'external_document',
        1,
    ))->toThrow(LockTimeoutException::class);

    expect(StockReservation::query()->count())->toBe(0);

    $held->release();

    $this->service->reserve($this->company->id, $this->item->id, '1.0000', StockReservationState::Hard, 'external_document', 1);

    $probe = Cache::lock($key, 10);
    expect($probe->get())->toBeTrue();
    $probe->release();
});

it('reserve frees the lock when stock is insufficient', function (): void {
    reservation_test_on_hand($this->company, $this->item, '1.0000');
    $key = "erp:stock-reservation:{$this->company->id}:{$this->item->id}";

    try {
        $this->service->reserve($this->company->id, $this->item->id, '2.0000', StockReservationState::Hard, 'external_document', 1);
    } catch (InsufficientStockException) {
    }

    $probe = Cache::lock($key, 10);
    expect($probe->get())->toBeTrue();
    $probe->release();
});

it('reserve rejects a non-positive quantity and a terminal mode', function (): void {
    reservation_test_on_hand($this->company, $this->item, '5.0000');

    expect(fn () => $this->service->reserve($this->company->id, $this->item->id, '0', StockReservationState::Hard, 'external_document', 1))
        ->toThrow(ValidationException::class)
        ->and(fn () => $this->service->reserve($this->company->id, $this->item->id, '-1.0000', StockReservationState::Hard, 'external_document', 1))
        ->toThrow(ValidationException::class)
        ->and(fn () => $this->service->reserve($this->company->id, $this->item->id, '1.0000', StockReservationState::Consumed, 'external_document', 1))
        ->toThrow(InvalidArgumentException::class);

    expect(StockReservation::query()->count())->toBe(0);
});

it('reserve rejects an item or warehouse of another company', function (): void {
    reservation_test_on_hand($this->company, $this->item, '5.0000');
    $other_company = Company::factory()->create();
    $foreign_warehouse = Warehouse::factory()->create(['company_id' => $other_company->id]);

    expect(fn () => $this->service->reserve($other_company->id, $this->item->id, '1.0000', StockReservationState::Hard, 'external_document', 1))
        ->toThrow(ValidationException::class)
        ->and(fn () => $this->service->reserve(
            $this->company->id,
            $this->item->id,
            '1.0000',
            StockReservationState::Hard,
            'external_document',
            1,
            warehouseId: $foreign_warehouse->id,
        ))->toThrow(ValidationException::class);

    expect(StockReservation::query()->count())->toBe(0);
});

it('promoteToHard flips live softs to hard, clears the expiry and skips expired ones', function (): void {
    $live = StockReservation::factory()->soft()->create([
        'company_id' => $this->company->id,
        'item_id' => $this->item->id,
        'source_type' => 'external_document',
        'source_id' => 10,
        'expires_at' => now()->addHour(),
    ]);
    $no_expiry = StockReservation::factory()->soft()->create([
        'company_id' => $this->company->id,
        'item_id' => $this->item->id,
        'source_type' => 'external_document',
        'source_id' => 10,
        'expires_at' => null,
    ]);
    $expired = StockReservation::factory()->soft()->create([
        'company_id' => $this->company->id,
        'item_id' => $this->item->id,
        'source_type' => 'external_document',
        'source_id' => 10,
        'expires_at' => now()->subMinute(),
    ]);
    $other_source = StockReservation::factory()->soft()->create([
        'company_id' => $this->company->id,
        'item_id' => $this->item->id,
        'source_type' => 'external_document',
        'source_id' => 11,
        'expires_at' => now()->addHour(),
    ]);
    $other_company = StockReservation::factory()->soft()->create([
        'source_type' => 'external_document',
        'source_id' => 10,
        'expires_at' => now()->addHour(),
    ]);

    $this->service->promoteToHard('external_document', 10, $this->company->id);

    expect($live->refresh()->state)->toBe(StockReservationState::Hard)
        ->and($live->expires_at)->toBeNull()
        ->and($no_expiry->refresh()->state)->toBe(StockReservationState::Hard)
        ->and($expired->refresh()->state)->toBe(StockReservationState::Soft)
        ->and($expired->expires_at)->not->toBeNull()
        ->and($other_source->refresh()->state)->toBe(StockReservationState::Soft)
        ->and($other_company->refresh()->state)->toBe(StockReservationState::Soft);
});

it('release restores availability and is idempotent', function (): void {
    reservation_test_on_hand($this->company, $this->item, '10.0000');

    $this->service->reserve($this->company->id, $this->item->id, '3.0000', StockReservationState::Hard, 'external_document', 5);
    $this->service->reserve($this->company->id, $this->item->id, '2.0000', StockReservationState::Soft, 'external_document', 5, expiresAt: now()->addHour());
    $this->service->reserve($this->company->id, $this->item->id, '1.0000', StockReservationState::Hard, 'external_document', 6);

    expect($this->service->available($this->company->id, $this->item->id))->toBe('4.0000');

    $this->service->release('external_document', 5);

    expect($this->service->available($this->company->id, $this->item->id))->toBe('9.0000')
        ->and(StockReservation::query()->where('source_id', 5)->pluck('state')->all())
        ->each->toBe(StockReservationState::Released);

    $this->service->release('external_document', 5);

    expect($this->service->available($this->company->id, $this->item->id))->toBe('9.0000')
        ->and(StockReservation::query()->where('source_id', 6)->firstOrFail()->state)->toBe(StockReservationState::Hard);
});

it('release leaves consumed reservations untouched', function (): void {
    $consumed = StockReservation::factory()->consumed()->create([
        'company_id' => $this->company->id,
        'item_id' => $this->item->id,
        'source_type' => 'external_document',
        'source_id' => 5,
    ]);

    $this->service->release('external_document', 5);

    expect($consumed->refresh()->state)->toBe(StockReservationState::Consumed);
});

it('consume closes the reserved quantity', function (): void {
    reservation_test_on_hand($this->company, $this->item, '10.0000');
    $this->service->reserve($this->company->id, $this->item->id, '5.0000', StockReservationState::Hard, 'external_document', 20);

    $this->service->consume('external_document', 20, '5.0000');

    $rows = StockReservation::query()->where('source_id', 20)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->state)->toBe(StockReservationState::Consumed)
        ->and($rows->first()->quantity)->toBe('5.0000')
        ->and($this->service->available($this->company->id, $this->item->id))->toBe('10.0000');
});

it('consume splits a row when only part of it is consumed', function (): void {
    reservation_test_on_hand($this->company, $this->item, '10.0000');
    $this->service->reserve($this->company->id, $this->item->id, '5.0000', StockReservationState::Hard, 'external_document', 21);

    $this->service->consume('external_document', 21, '2.0000');

    $hard = StockReservation::query()->where('source_id', 21)->where('state', StockReservationState::Hard)->get();
    $consumed = StockReservation::query()->where('source_id', 21)->where('state', StockReservationState::Consumed)->get();

    expect($hard)->toHaveCount(1)
        ->and($hard->first()->quantity)->toBe('3.0000')
        ->and($consumed)->toHaveCount(1)
        ->and($consumed->first()->quantity)->toBe('2.0000')
        ->and($consumed->first()->company_id)->toBe($this->company->id)
        ->and($consumed->first()->item_id)->toBe($this->item->id)
        ->and($this->service->available($this->company->id, $this->item->id))->toBe('7.0000');
});

it('consume spans several hard rows of the same source, oldest first', function (): void {
    reservation_test_on_hand($this->company, $this->item, '20.0000');
    $this->service->reserve($this->company->id, $this->item->id, '3.0000', StockReservationState::Hard, 'external_document', 22);
    $this->service->reserve($this->company->id, $this->item->id, '4.0000', StockReservationState::Hard, 'external_document', 22);

    $this->service->consume('external_document', 22, '5.0000');

    $live = StockReservation::query()->where('source_id', 22)->where('state', StockReservationState::Hard)->get();
    $consumed = StockReservation::query()->where('source_id', 22)->where('state', StockReservationState::Consumed)->orderBy('id')->pluck('quantity')->all();

    expect($live)->toHaveCount(1)
        ->and($live->first()->quantity)->toBe('2.0000')
        ->and($consumed)->toBe(['3.0000', '2.0000']);
});

it('consume rejects more than reserved', function (): void {
    reservation_test_on_hand($this->company, $this->item, '10.0000');
    $this->service->reserve($this->company->id, $this->item->id, '3.0000', StockReservationState::Hard, 'external_document', 23);

    expect(fn () => $this->service->consume('external_document', 23, '3.0001'))
        ->toThrow(ValidationException::class);

    $row = StockReservation::query()->where('source_id', 23)->firstOrFail();

    expect($row->state)->toBe(StockReservationState::Hard)
        ->and($row->quantity)->toBe('3.0000')
        ->and(StockReservation::query()->where('source_id', 23)->count())->toBe(1);
});

it('consume rejects a source with no hard reservation, a closed one included', function (): void {
    reservation_test_on_hand($this->company, $this->item, '10.0000');
    $this->service->reserve($this->company->id, $this->item->id, '3.0000', StockReservationState::Soft, 'external_document', 24, expiresAt: now()->addHour());
    $this->service->reserve($this->company->id, $this->item->id, '3.0000', StockReservationState::Hard, 'external_document', 25);
    $this->service->consume('external_document', 25, '3.0000');

    expect(fn () => $this->service->consume('external_document', 24, '1.0000'))->toThrow(ValidationException::class)
        ->and(fn () => $this->service->consume('external_document', 25, '1.0000'))->toThrow(ValidationException::class)
        ->and(fn () => $this->service->consume('external_document', 999, '1.0000'))->toThrow(ValidationException::class)
        ->and(fn () => $this->service->consume('external_document', 25, '0'))->toThrow(ValidationException::class);
});
