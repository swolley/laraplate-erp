<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\ERP\Casts\DeliveryNoteDirection;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Policies\ERPModelPolicy;

uses(RefreshDatabase::class);

function statePolicyCompany(): Company
{
    return Company::query()->create([
        'slug' => 'state-policy-' . uniqid(),
        'name' => 'State Policy Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
}

function grantStatePolicyPermission(User $user, object $record, string $operation): void
{
    $permission = sprintf(
        '%s.%s.%s',
        $record->getConnectionName() ?? 'default',
        $record->getTable(),
        $operation,
    );
    Permission::findOrCreate($permission, 'web');
    $user->givePermissionTo($permission);
}

it('denies invoice post when already posted even for superadmin', function (): void {
    $company = statePolicyCompany();
    $party = Party::query()->create(['company_id' => $company->id, 'name' => 'P', 'is_customer' => true]);
    $entry = JournalEntry::query()->create(['company_id' => $company->id]);
    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
        'journal_entry_id' => $entry->id,
    ]);

    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    expect(app(ERPModelPolicy::class)->post($user, $invoice))->toBeFalse();
});

it('allows invoice post when draft and user has permission', function (): void {
    $company = statePolicyCompany();
    $party = Party::query()->create(['company_id' => $company->id, 'name' => 'P', 'is_customer' => true]);
    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
        'journal_entry_id' => null,
    ]);

    $user = User::factory()->create();
    grantStatePolicyPermission($user, $invoice, 'post');

    expect(app(ERPModelPolicy::class)->post($user, $invoice))->toBeTrue();
});

it('denies fiscal period close when already closed', function (): void {
    FiscalPeriod::disableVersioning();
    $company = statePolicyCompany();
    $year = FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_closed' => false,
    ]);
    $period = FiscalPeriod::query()->create([
        'fiscal_year_id' => $year->id,
        'period_no' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'is_closed' => true,
    ]);
    FiscalPeriod::enableVersioning();

    $user = User::factory()->create();
    grantStatePolicyPermission($user, $period, 'close');

    expect(app(ERPModelPolicy::class)->close($user, $period))->toBeFalse();
});

it('allows fiscal period reopen only when closed', function (): void {
    FiscalPeriod::disableVersioning();
    $company = statePolicyCompany();
    $year = FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_closed' => false,
    ]);
    $open = FiscalPeriod::query()->create([
        'fiscal_year_id' => $year->id,
        'period_no' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'is_closed' => false,
    ]);
    $closed = FiscalPeriod::query()->create([
        'fiscal_year_id' => $year->id,
        'period_no' => 2,
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-28',
        'is_closed' => true,
    ]);
    FiscalPeriod::enableVersioning();

    $user = User::factory()->create();
    grantStatePolicyPermission($user, $closed, 'reopen');

    $policy = app(ERPModelPolicy::class);

    expect($policy->reopen($user, $open))->toBeFalse()
        ->and($policy->reopen($user, $closed))->toBeTrue();
});

it('allows fiscal year close through the registered policy only when open', function (): void {
    FiscalYear::disableVersioning();
    $company = statePolicyCompany();
    $open = FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_closed' => false,
    ]);
    $closed = FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => 2027,
        'start_date' => '2027-01-01',
        'end_date' => '2027-12-31',
        'is_closed' => true,
    ]);
    FiscalYear::enableVersioning();

    $user = User::factory()->create();
    grantStatePolicyPermission($user, $open, 'close');

    expect(Gate::forUser($user)->allows('close', $open))->toBeTrue()
        ->and(Gate::forUser($user)->allows('close', $closed))->toBeFalse();
});

it('denies journal reverse when entry is not posted', function (): void {
    $company = statePolicyCompany();
    $entry = JournalEntry::query()->create([
        'company_id' => $company->id,
        'posted_at' => null,
    ]);

    $user = User::factory()->create();
    grantStatePolicyPermission($user, $entry, 'reverse');

    expect(app(ERPModelPolicy::class)->reverse($user, $entry))->toBeFalse();
});

it('denies sales order amend when status is draft', function (): void {
    $company = statePolicyCompany();
    $party = Party::query()->create(['company_id' => $company->id, 'name' => 'P', 'is_customer' => true]);
    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::Draft,
    ]);

    $user = User::factory()->create();
    grantStatePolicyPermission($user, $order, 'amend');

    expect(app(ERPModelPolicy::class)->amend($user, $order))->toBeFalse();
});

it('allows forcePost only on draft purchase invoices', function (): void {
    $company = statePolicyCompany();
    $party = Party::query()->create(['company_id' => $company->id, 'name' => 'P', 'is_supplier' => true]);
    $purchase = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Purchase,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
        'journal_entry_id' => null,
    ]);
    $sale = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
        'journal_entry_id' => null,
    ]);

    $user = User::factory()->create();
    grantStatePolicyPermission($user, $purchase, 'force_post');

    $policy = app(ERPModelPolicy::class);

    expect($policy->forcePost($user, $purchase))->toBeTrue()
        ->and($policy->forcePost($user, $sale))->toBeFalse();
});

it('denies delivery note post when already posted', function (): void {
    $company = statePolicyCompany();
    $note = DeliveryNote::query()->create([
        'company_id' => $company->id,
        'direction' => DeliveryNoteDirection::Outbound,
        'posted_at' => now(),
    ]);

    $user = User::factory()->create();
    grantStatePolicyPermission($user, $note, 'post');

    expect(app(ERPModelPolicy::class)->post($user, $note))->toBeFalse();
});
