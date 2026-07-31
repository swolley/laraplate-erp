<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\Core\Services\Crud\DomainActionRegistry;
use Modules\Core\Support\PermissionName;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\ReturnOrder;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SupplierReturn;

uses(RefreshDatabase::class);

it('registers the accounting domain actions at boot', function (): void {
    $registry = app(DomainActionRegistry::class);

    expect($registry->has(Invoice::class, 'post'))->toBeTrue()
        ->and($registry->has(Invoice::class, 'unpost'))->toBeTrue()
        ->and($registry->has(Invoice::class, 'submitEInvoice'))->toBeTrue()
        ->and($registry->has(JournalEntry::class, 'reverse'))->toBeTrue()
        ->and($registry->has(FiscalPeriod::class, 'close'))->toBeTrue()
        ->and($registry->has(FiscalPeriod::class, 'reopen'))->toBeTrue()
        ->and($registry->has(FiscalYear::class, 'close'))->toBeTrue()
        ->and($registry->has(SalesOrder::class, 'amend'))->toBeTrue()
        ->and($registry->has(DeliveryNote::class, 'post'))->toBeTrue()
        ->and($registry->has(DeliveryNote::class, 'unpost'))->toBeTrue();
});

it('declares approve as an overridden generic verb on both returns', function (): void {
    expect(ReturnOrder::overriddenCrudActions())->toBe(['approve'])
        ->and(SupplierReturn::overriddenCrudActions())->toBe(['approve']);
});

it('registers the return lifecycle actions', function (): void {
    $registry = app(DomainActionRegistry::class);

    expect($registry->has(ReturnOrder::class, 'approve'))->toBeTrue()
        ->and($registry->has(ReturnOrder::class, 'complete'))->toBeTrue()
        ->and($registry->has(ReturnOrder::class, 'cancel'))->toBeTrue()
        ->and($registry->has(ReturnOrder::class, 'reverse_processed'))->toBeTrue()
        ->and($registry->has(ReturnOrder::class, 'create_credit_note'))->toBeTrue()
        ->and($registry->has(SupplierReturn::class, 'approve'))->toBeTrue()
        ->and($registry->has(SupplierReturn::class, 'complete'))->toBeTrue()
        ->and($registry->has(SupplierReturn::class, 'cancel'))->toBeTrue()
        ->and($registry->has(SupplierReturn::class, 'reverse_processed'))->toBeTrue()
        ->and($registry->has(SupplierReturn::class, 'create_debit_note'))->toBeTrue();
});

it('seeds the return domain permissions', function (): void {
    $this->seed(Modules\ERP\Database\Seeders\ERPDatabaseSeeder::class);

    expect(Permission::query()->where('name', PermissionName::forClass(ReturnOrder::class, 'approve'))->exists())->toBeTrue()
        ->and(Permission::query()->where('name', PermissionName::forClass(SupplierReturn::class, 'complete'))->exists())->toBeTrue();
});

it('does not register force_post as an action of its own', function (): void {
    // Forcing the three-way match is a flag on `post` guarded by the `forcePost`
    // permission, mirroring the Filament form. A separate action would be a way
    // to post that bypasses the normal path.
    expect(app(DomainActionRegistry::class)->has(Invoice::class, 'force_post'))->toBeFalse();
});
