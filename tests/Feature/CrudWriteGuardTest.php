<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Modules\Core\Contracts\RestrictsCrudWrites;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
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

it('marks the immutable/derived ERP models as write-restricted', function (): void {
    foreach ([JournalEntry::class, JournalEntryLine::class, VatRegisterEntry::class, StockMovement::class, StockCostLayer::class, StockLevel::class] as $model) {
        $instance = new $model;
        expect($instance)->toBeInstanceOf(RestrictsCrudWrites::class)
            ->and($instance->deniedCrudWrites())->toContain('insert')
            ->and($instance->deniedCrudWrites())->toContain('update')
            ->and($instance->deniedCrudWrites())->toContain('delete');
    }
});

it('leaves an ordinary editable ERP model unrestricted', function (): void {
    expect(new Invoice)->not->toBeInstanceOf(RestrictsCrudWrites::class);
});

it('returns 403 and persists nothing when inserting a journal entry via generic CRUD as superadmin', function (): void {
    $company = Modules\ERP\Models\Company::query()->create([
        'slug' => 'crud-guard-co',
        'name' => 'Crud Guard Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

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
