<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\ERP\Filament\Resources\DocumentSequences\Actions\DocumentSequenceActions;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Policies\ERPModelPolicy;
use Modules\ERP\Services\Accounting\DocumentNumberAllocator;
use Modules\ERP\Services\Accounting\DocumentSequenceResetService;

uses(RefreshDatabase::class);

function documentSequenceResetCompany(): Company
{
    return Company::query()->create([
        'slug' => 'seq-reset-' . uniqid(),
        'name' => 'Seq Reset Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
}

function documentSequenceResetRecord(?Company $company = null): DocumentSequence
{
    $company ??= documentSequenceResetCompany();

    return DocumentSequence::query()->create([
        'company_id' => $company->id,
        'document_type' => DocumentType::SalesOrder,
        'fiscal_year' => 2026,
        'last_number' => 42,
        'gap_allowed' => true,
        'prefix' => 'SO-',
        'padding' => 4,
        'suffix' => '',
    ]);
}

function documentSequenceResetPermission(DocumentSequence $sequence): string
{
    return sprintf(
        '%s.%s.%s',
        $sequence->getConnectionName() ?? 'default',
        $sequence->getTable(),
        'reset',
    );
}

it('resets last number and next allocation uses the new counter', function (): void {
    $company = documentSequenceResetCompany();
    $sequence = documentSequenceResetRecord($company);

    resolve(DocumentSequenceResetService::class)->reset($sequence, 0);

    $sequence->refresh();
    $next = resolve(DocumentNumberAllocator::class)->next($company, DocumentType::SalesOrder, 2026);

    expect($sequence->last_number)->toBe(0)
        ->and($next)->toBe('SO-2026-0001');
});

it('rejects negative reset counters', function (): void {
    $sequence = documentSequenceResetRecord();

    expect(fn () => resolve(DocumentSequenceResetService::class)->reset($sequence, -1))
        ->toThrow(ValidationException::class, 'cannot be negative');
});

it('seeds document sequence reset permission', function (): void {
    $this->seed(ERPDatabaseSeeder::class);

    expect(Permission::query()->where('name', 'default.erp_document_sequences.reset')->exists())->toBeTrue();
});

it('allows reset when user has permission', function (): void {
    $sequence = documentSequenceResetRecord();
    $permission = documentSequenceResetPermission($sequence);
    $guard = config('auth.defaults.guard');
    $guard = is_string($guard) ? $guard : 'web';
    $user = User::factory()->create();

    Permission::findOrCreate($permission, $guard);
    $user->givePermissionTo($permission);

    expect(app(ERPModelPolicy::class)->reset($user, $sequence))->toBeTrue();
});

it('exposes document sequence reset action factory', function (): void {
    expect(DocumentSequenceActions::reset()->getName())->toBe('reset');
});
