<?php

declare(strict_types=1);

/**
 * The lock guard triggers only exist on MySQL and PostgreSQL: the migration is a no-op on SQLite,
 * which is what the suite runs on, so their behaviour cannot be exercised here. These assertions
 * therefore read the DDL the migration emits.
 *
 * What they protect is the rule that a lease owner is never blocked by their own lease. A trigger
 * cannot know the acting user, so it must key on the lock being ownerless. A guard that blocks on
 * the bare presence of `locked_at` would make the four ERP documents the only lockable models on
 * which a user cannot edit the record they took charge of.
 */
function lockGuardMigrationSource(): string
{
    return (string) file_get_contents(
        module_path('ERP', 'database/migrations/2026_05_07_200000_create_lock_guard_triggers.php'),
    );
}

it('guards only ownerless locks, and only while they have not expired', function (): void {
    $source = lockGuardMigrationSource();

    expect($source)->toContain('OLD.locked_user_id IS NULL')
        ->and($source)->toContain('OLD.locked_until IS NULL OR OLD.locked_until > CURRENT_TIMESTAMP');
});

it('leaves no guard keyed on the bare presence of a lock', function (): void {
    $source = lockGuardMigrationSource();

    // `OLD.locked_at IS NOT NULL` may appear only inside the shared predicate, where the ownerless
    // and not-expired clauses follow it. Any other occurrence is an owner-blind guard.
    $occurrences = mb_substr_count($source, 'OLD.locked_at IS NOT NULL');

    expect($occurrences)->toBe(1);
});

it('locks documents ownerlessly when a sales order is confirmed', function (): void {
    $source = lockGuardMigrationSource();

    // The chain triggers must keep writing `locked_at` alone. Were they to stamp a user, the
    // documents they protect would become leases and the guards above would stop blocking them.
    expect($source)->toContain('SET locked_at = COALESCE(locked_at, CURRENT_TIMESTAMP)')
        ->and($source)->not->toContain('SET locked_at = COALESCE(locked_at, CURRENT_TIMESTAMP), locked_user_id');
});
