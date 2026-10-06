<?php

declare(strict_types=1);

use function Modules\ERP\Helpers\with_company;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\ERP\Enums\StockReservationState;
use Modules\ERP\Models\StockReservation;
use Symfony\Component\Console\Command\Command;

uses(RefreshDatabase::class);

it('discovers the ERP stock reservation expiry command', function (): void {
    expect(Artisan::all())->toHaveKey('erp:stock-reservations:expire');
});

it('releases soft reservations whose expiry has passed', function (): void {
    $expired = StockReservation::factory()->soft()->create(['expires_at' => now()->subMinute()]);

    $this->artisan('erp:stock-reservations:expire')->assertExitCode(Command::SUCCESS);

    expect(StockReservation::query()->findOrFail($expired->id)->state)->toBe(StockReservationState::Released);
});

it('leaves live soft, hard, consumed and already released reservations untouched', function (): void {
    $future_soft = StockReservation::factory()->soft()->create(['expires_at' => now()->addHour()]);
    $open_ended_soft = StockReservation::factory()->soft()->create(['expires_at' => null]);
    $hard = StockReservation::factory()->hard()->create(['expires_at' => null]);
    $hard_with_stale_expiry = StockReservation::factory()->hard()->create(['expires_at' => now()->subDay()]);
    $consumed = StockReservation::factory()->consumed()->create(['expires_at' => now()->subDay()]);
    $released = StockReservation::factory()->released()->create(['expires_at' => now()->subDay()]);

    $this->artisan('erp:stock-reservations:expire')->assertExitCode(Command::SUCCESS);

    expect(StockReservation::query()->findOrFail($future_soft->id)->state)->toBe(StockReservationState::Soft)
        ->and(StockReservation::query()->findOrFail($open_ended_soft->id)->state)->toBe(StockReservationState::Soft)
        ->and(StockReservation::query()->findOrFail($hard->id)->state)->toBe(StockReservationState::Hard)
        ->and(StockReservation::query()->findOrFail($hard_with_stale_expiry->id)->state)->toBe(StockReservationState::Hard)
        ->and(StockReservation::query()->findOrFail($consumed->id)->state)->toBe(StockReservationState::Consumed)
        ->and(StockReservation::query()->findOrFail($released->id)->state)->toBe(StockReservationState::Released);
});

it('reports how many reservations it released', function (): void {
    StockReservation::factory()->soft()->count(2)->create(['expires_at' => now()->subMinutes(5)]);
    StockReservation::factory()->soft()->create(['expires_at' => now()->addMinutes(5)]);
    StockReservation::factory()->hard()->create();

    $this->artisan('erp:stock-reservations:expire')
        ->expectsOutputToContain('Released 2 expired soft stock reservations.')
        ->assertExitCode(Command::SUCCESS);

    expect(StockReservation::query()->where('state', StockReservationState::Released->value)->count())->toBe(2);
});

it('reports zero and stays idempotent when nothing is left to expire', function (): void {
    StockReservation::factory()->soft()->create(['expires_at' => now()->subMinute()]);

    $this->artisan('erp:stock-reservations:expire')
        ->expectsOutputToContain('Released 1 expired soft stock reservations.')
        ->assertExitCode(Command::SUCCESS);

    $this->artisan('erp:stock-reservations:expire')
        ->expectsOutputToContain('Released 0 expired soft stock reservations.')
        ->assertExitCode(Command::SUCCESS);
});

it('sweeps every company regardless of the ambient company context', function (): void {
    $first = StockReservation::factory()->soft()->create(['expires_at' => now()->subMinute()]);
    $second = StockReservation::factory()->soft()->create(['expires_at' => now()->subMinute()]);

    expect($first->company_id)->not->toBe($second->company_id);

    with_company((int) $first->company_id, function (): void {
        Artisan::call('erp:stock-reservations:expire');
    });

    expect(StockReservation::query()->findOrFail($first->id)->state)->toBe(StockReservationState::Released)
        ->and(StockReservation::query()->findOrFail($second->id)->state)->toBe(StockReservationState::Released);
});

it('exposes the soft reservation TTL as a positive number of minutes', function (): void {
    $ttl = config('erp.stock_reservation.soft_ttl');

    expect($ttl)->toBeInt()->toBeGreaterThan(0);
});

it('schedules the sweep hourly', function (): void {
    $events = collect(resolve(Schedule::class)->events())
        ->filter(static fn (Event $event): bool => str_contains((string) $event->command, 'erp:stock-reservations:expire'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 * * * *');
});
