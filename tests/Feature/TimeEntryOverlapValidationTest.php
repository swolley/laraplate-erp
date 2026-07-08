<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Modules\Core\Models\User;
use Modules\Core\Overrides\ContextualValidationException;
use Modules\ERP\Models\TimeEntry;
use Modules\ERP\Tests\Support\ActivityTaxonomy;

uses(RefreshDatabase::class);

function createTimeEntryForOverlapTests(int $user_id, int $taxonomy_id, string $started_at, ?string $ended_at): TimeEntry
{
    return TimeEntry::query()->create([
        'user_id' => $user_id,
        'taxonomy_id' => $taxonomy_id,
        'started_at' => $started_at,
        'ended_at' => $ended_at,
    ]);
}

it('rejects overlapping ended_at updates through has validations update rules', function (): void {
    $taxonomy_id = ActivityTaxonomy::insertMinimalId('activity-overlap-ended-at');
    $user = User::factory()->create();

    $existing = createTimeEntryForOverlapTests($user->id, $taxonomy_id, '2026-06-01 09:00:00', '2026-06-01 10:00:00');
    createTimeEntryForOverlapTests($user->id, $taxonomy_id, '2026-06-01 11:00:00', '2026-06-01 12:00:00');

    expect(fn () => $existing->update(['ended_at' => '2026-06-01 11:30:00']))
        ->toThrow(ContextualValidationException::class, 'overlaps with another time entry');
});

it('rejects overlapping started_at updates through has validations update rules', function (): void {
    $taxonomy_id = ActivityTaxonomy::insertMinimalId('activity-overlap-started-at');
    $user = User::factory()->create();

    createTimeEntryForOverlapTests($user->id, $taxonomy_id, '2026-06-02 09:00:00', '2026-06-02 10:00:00');
    $candidate = createTimeEntryForOverlapTests($user->id, $taxonomy_id, '2026-06-02 14:00:00', '2026-06-02 15:00:00');

    expect(fn () => $candidate->update(['started_at' => '2026-06-02 09:30:00']))
        ->toThrow(ContextualValidationException::class, 'overlaps with another time entry');
});

it('allows non overlapping time entry updates', function (): void {
    $taxonomy_id = ActivityTaxonomy::insertMinimalId('activity-overlap-success');
    $user = User::factory()->create();

    createTimeEntryForOverlapTests($user->id, $taxonomy_id, '2026-06-03 09:00:00', '2026-06-03 10:00:00');
    $candidate = createTimeEntryForOverlapTests($user->id, $taxonomy_id, '2026-06-03 11:00:00', '2026-06-03 12:00:00');

    $candidate->update(['ended_at' => '2026-06-03 12:30:00']);

    $candidate->refresh();

    expect($candidate->ended_at)->not->toBeNull();
    expect(Date::parse((string) $candidate->ended_at)->format('Y-m-d H:i:s'))->toBe('2026-06-03 12:30:00');
});

it('does not treat a time entry as overlapping itself on update', function (): void {
    $taxonomy_id = ActivityTaxonomy::insertMinimalId('activity-overlap-self');
    $user = User::factory()->create();

    $entry = createTimeEntryForOverlapTests($user->id, $taxonomy_id, '2026-06-04 09:00:00', '2026-06-04 10:00:00');

    $entry->update(['ended_at' => '2026-06-04 10:30:00']);

    $entry->refresh();

    expect(Date::parse((string) $entry->ended_at)->format('Y-m-d H:i:s'))->toBe('2026-06-04 10:30:00');
});
