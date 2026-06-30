<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Modules\Core\Contracts\RestrictsCrudWrites;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\JournalEntryLine;
use Modules\ERP\Models\StockCostLayer;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\StockMovement;
use Modules\ERP\Models\VatRegisterEntry;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('core.expose_crud_api', true);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));
    $this->actingAs($this->user);
});

function createCrudGuardCompany(): Company
{
    return Company::query()->create([
        'slug' => 'crud-guard-co',
        'name' => 'Crud Guard Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
}

function createCrudGuardJournalEntry(?Company $company = null): JournalEntry
{
    return JournalEntry::query()->create([
        'company_id' => ($company ?? createCrudGuardCompany())->id,
        'description' => 'Original description',
    ]);
}

it('marks the immutable/derived ERP models as write-restricted', function (): void {
    foreach ([JournalEntry::class, JournalEntryLine::class, VatRegisterEntry::class, StockMovement::class, StockCostLayer::class, StockLevel::class] as $model) {
        $instance = new $model;
        expect($instance)->toBeInstanceOf(RestrictsCrudWrites::class)
            ->and($instance->deniedCrudWrites())->toContain('insert')
            ->and($instance->deniedCrudWrites())->toContain('update')
            ->and($instance->deniedCrudWrites())->toContain('delete')
            ->and($instance->deniedCrudWrites())->toContain('forceDelete')
            ->and($instance->deniedCrudWrites())->toContain('restore')
            ->and($instance->deniedCrudWrites())->toContain('approve')
            ->and($instance->deniedCrudWrites())->toContain('disapprove')
            ->and($instance->deniedCrudWrites())->toContain('lock')
            ->and($instance->deniedCrudWrites())->toContain('unlock');
    }
});

it('leaves an ordinary editable ERP model unrestricted', function (): void {
    expect(new Invoice)->not->toBeInstanceOf(RestrictsCrudWrites::class);
});

it('returns 403 and persists nothing when inserting a journal entry via generic CRUD as superadmin', function (): void {
    $company = createCrudGuardCompany();

    // The generic CRUD routes are registered by Core; the module is a URL parameter,
    // so the route name is "core.api.insert" with module=ERP (NOT "erp.api.insert").
    // The module is passed as "ERP" because CrudRequest studly-cases it to resolve the
    // registered module name. JournalEntry's only required create field is company_id, so
    // validation passes and execution reaches the CrudService write guard.
    $response = $this->postJson(
        route('core.api.insert', ['module' => 'ERP', 'entity' => 'journal_entries']),
        ['company_id' => $company->id],
    );

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    expect(JournalEntry::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('resolves lowercase ERP module names before applying the generic CRUD write guard', function (): void {
    $company = createCrudGuardCompany();

    $response = $this->postJson(
        route('core.api.insert', ['module' => 'erp', 'entity' => 'journal_entries']),
        ['company_id' => $company->id],
    );

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    expect(JournalEntry::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('returns 403 and leaves a journal entry unchanged when updating via generic CRUD as superadmin', function (): void {
    $entry = createCrudGuardJournalEntry();

    $response = $this->putJson(
        route('core.api.replace', ['module' => 'ERP', 'entity' => 'journal_entries', 'id' => $entry->id]),
        ['description' => 'Changed through CRUD'],
    );

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    expect($entry->fresh()->description)->toBe('Original description');
});

it('returns 403 and leaves a journal entry in place when deleting via generic CRUD as superadmin', function (): void {
    $entry = createCrudGuardJournalEntry();

    $response = $this->deleteJson(
        route('core.api.delete', ['module' => 'ERP', 'entity' => 'journal_entries', 'id' => $entry->id]),
    );

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    expect(JournalEntry::query()->withoutGlobalScopes()->whereKey($entry->id)->exists())->toBeTrue();
});

it('returns 403 when activating a restricted journal entry through generic CRUD as superadmin', function (): void {
    $entry = createCrudGuardJournalEntry();

    $response = $this->patchJson(
        route('core.crud.activate', ['module' => 'ERP', 'entity' => 'journal_entries', 'id' => $entry->id]),
    );

    $response->assertStatus(Response::HTTP_FORBIDDEN);
});

it('returns 403 when approving a restricted journal entry through generic CRUD as superadmin', function (): void {
    $entry = createCrudGuardJournalEntry();

    $response = $this->patchJson(
        route('core.crud.approve', ['module' => 'ERP', 'entity' => 'journal_entries', 'id' => $entry->id]),
    );

    $response->assertStatus(Response::HTTP_FORBIDDEN);
});

it('returns 403 when locking a restricted journal entry through generic CRUD as superadmin', function (): void {
    $entry = createCrudGuardJournalEntry();

    $response = $this->patchJson(
        route('core.crud.lock', ['module' => 'ERP', 'entity' => 'journal_entries', 'id' => $entry->id]),
    );

    $response->assertStatus(Response::HTTP_FORBIDDEN);
});
