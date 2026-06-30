<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Policies\ERPModelPolicy;

uses(RefreshDatabase::class);

function policyInvoice(): Invoice
{
    $company = Company::query()->create([
        'slug' => 'policy-co',
        'name' => 'Policy Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    return Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => Modules\ERP\Casts\InvoiceDirection::Sale,
        'invoice_type' => Modules\ERP\Casts\InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);
}

/**
 * Mirror the permission string built by {@see ERPModelPolicy::allows()} so the
 * test stays correct regardless of the active connection (e.g. sqlite in tests
 * vs the default connection in production).
 */
function policyPermission(Invoice $invoice, string $operation): string
{
    return sprintf(
        '%s.%s.%s',
        $invoice->getConnectionName() ?? 'default',
        $invoice->getTable(),
        $operation,
    );
}

it('allows superadmin to run every ERP ability', function (): void {
    $invoice = policyInvoice();
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    $policy = app(ERPModelPolicy::class);

    expect($policy->post($user, $invoice))->toBeTrue()
        ->and($policy->unpost($user, $invoice))->toBeTrue()
        ->and($policy->submitEInvoice($user, $invoice))->toBeTrue()
        ->and($policy->refreshEInvoice($user, $invoice))->toBeTrue();
});

it('allows a user granted the specific permission', function (): void {
    $invoice = policyInvoice();
    $permission = policyPermission($invoice, 'submitEInvoice');
    Permission::findOrCreate($permission, 'web');

    $user = User::factory()->create();
    $user->givePermissionTo($permission);

    expect(app(ERPModelPolicy::class)->submitEInvoice($user, $invoice))->toBeTrue();
});

it('denies a user who lacks the permission even when the permission row exists', function (): void {
    $invoice = policyInvoice();
    Permission::findOrCreate(policyPermission($invoice, 'submitEInvoice'), 'web');

    $user = User::factory()->create();

    expect(app(ERPModelPolicy::class)->submitEInvoice($user, $invoice))->toBeFalse();
});

it('denies (fail-closed) when the permission row is absent', function (): void {
    $invoice = policyInvoice();
    $user = User::factory()->create();

    expect(app(ERPModelPolicy::class)->refreshEInvoice($user, $invoice))->toBeFalse();
});
