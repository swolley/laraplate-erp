<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;

uses(RefreshDatabase::class);

it('seeds e-invoice domain permissions for the invoice model only', function (): void {
    $this->seed(ERPDatabaseSeeder::class);

    expect(Permission::query()->where('name', 'default.erp_invoices.post')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_invoices.unpost')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_invoices.submitEInvoice')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_invoices.refreshEInvoice')->exists())->toBeTrue();
});

it('does not seed e-invoice permissions for non-invoice models', function (): void {
    $this->seed(ERPDatabaseSeeder::class);

    expect(Permission::query()->where('name', 'default.erp_journal_entries.post')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_journal_entries.submitEInvoice')->exists())->toBeFalse();
});

it('seeds Phase 2A domain permissions for fiscal and commercial models', function (): void {
    $this->seed(ERPDatabaseSeeder::class);

    expect(Permission::query()->where('name', 'default.erp_fiscal_periods.close')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_fiscal_periods.reopen')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_fiscal_years.close')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_journal_entries.reverse')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_sales_orders.amend')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'default.erp_invoices.force_post')->exists())->toBeTrue();
});

it('does not seed force_post on non-invoice models', function (): void {
    $this->seed(ERPDatabaseSeeder::class);

    expect(Permission::query()->where('name', 'default.erp_sales_orders.force_post')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'default.erp_delivery_notes.force_post')->exists())->toBeFalse();
});
