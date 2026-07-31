<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Services\Crud\DomainActionRegistry;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\SalesOrder;

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

it('does not register force_post as an action of its own', function (): void {
    // Forcing the three-way match is a flag on `post` guarded by the `forcePost`
    // permission, mirroring the Filament form. A separate action would be a way
    // to post that bypasses the normal path.
    expect(app(DomainActionRegistry::class)->has(Invoice::class, 'force_post'))->toBeFalse();
});
