<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Exceptions\PostedJournalImmutableException;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\JournalEntry;

uses(RefreshDatabase::class);

it('links reversal vouchers to their original posted entry', function (): void {
    $company = Company::query()->create([
        'slug' => 'journal-rel-' . uniqid(),
        'name' => 'Journal Relations Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $original = JournalEntry::query()->create([
        'company_id' => $company->id,
        'description' => 'Original posting',
        'posted_at' => now(),
    ]);
    $reversal = JournalEntry::query()->create([
        'company_id' => $company->id,
        'description' => 'Reversal posting',
        'posted_at' => now(),
        'reverses_journal_entry_id' => $original->id,
        'reversal_reason' => 'Correction',
    ]);

    expect((new JournalEntry)->original_entry_reversed())->toBeInstanceOf(BelongsTo::class)
        ->and((new JournalEntry)->reversal_voucher())->toBeInstanceOf(HasOne::class)
        ->and($reversal->fresh()->original_entry_reversed?->id)->toBe($original->id)
        ->and($original->fresh()->reversal_voucher?->id)->toBe($reversal->id);
});

it('blocks mutating posted journal headers outside the posting service', function (): void {
    $company = Company::query()->create([
        'slug' => 'journal-immutable-' . uniqid(),
        'name' => 'Journal Immutable Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $entry = JournalEntry::query()->create([
        'company_id' => $company->id,
        'description' => 'Posted entry',
        'posted_at' => now(),
    ]);

    expect(fn () => $entry->update(['description' => 'Changed']))
        ->toThrow(PostedJournalImmutableException::class)
        ->and(fn () => $entry->delete())
        ->toThrow(PostedJournalImmutableException::class);
});

it('allows deleting draft journal headers', function (): void {
    $company = Company::query()->create([
        'slug' => 'journal-draft-' . uniqid(),
        'name' => 'Journal Draft Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $entry = JournalEntry::query()->create([
        'company_id' => $company->id,
        'description' => 'Draft entry',
    ]);

    $entry->delete();

    expect(JournalEntry::query()->find($entry->id))->toBeNull();
});
