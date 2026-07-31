<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;

uses(RefreshDatabase::class);

function domainActionUrl(string $action, string $entity): string
{
    return route('core.crud.domain-action', ['action' => $action, 'module' => 'erp', 'entity' => $entity]);
}

/**
 * A local copy rather than an import: Pest helpers are file-scoped and
 * ErpModelPolicyTest already declares one under a different name.
 */
function httpDomainActionInvoice(): Invoice
{
    $company = Company::query()->create([
        'slug' => 'http-action-co',
        'name' => 'Http Action Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    return Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);
}

function grantDomainPermission(User $user, Invoice $invoice, string $operation): void
{
    $name = PermissionName::forModel($invoice, $operation);
    Permission::findOrCreate($name, 'web');
    $user->givePermissionTo($name);
}

it('refuses a domain action to a user without the permission', function (): void {
    $invoice = httpDomainActionInvoice();

    $response = $this->actingAs(User::factory()->create())
        ->postJson(domainActionUrl('post', 'invoices'), ['id' => $invoice->id]);

    $response->assertUnauthorized();

    expect($invoice->fresh()?->posted_at)->toBeNull();
});

it('returns 404 for an action that is not registered on the entity', function (): void {
    $invoice = httpDomainActionInvoice();
    $user = User::factory()->create();
    grantDomainPermission($user, $invoice, 'post');

    $this->actingAs($user)
        ->postJson(domainActionUrl('settle_up', 'invoices'), ['id' => $invoice->id])
        ->assertNotFound();
});

it('returns 404 for a record that does not exist', function (): void {
    $invoice = httpDomainActionInvoice();
    $user = User::factory()->create();
    grantDomainPermission($user, $invoice, 'post');

    $this->actingAs($user)
        ->postJson(domainActionUrl('post', 'invoices'), ['id' => 999999])
        ->assertNotFound();
});

it('refuses unpost on an invoice that was never posted', function (): void {
    $invoice = httpDomainActionInvoice();
    $user = User::factory()->create();
    grantDomainPermission($user, $invoice, 'unpost');

    // The state guard lives in the policy, so an invalid transition is refused
    // before the service is reached.
    $this->actingAs($user)
        ->postJson(domainActionUrl('unpost', 'invoices'), ['id' => $invoice->id])
        ->assertUnauthorized();
});

it('refuses an action the user may not force', function (): void {
    $invoice = httpDomainActionInvoice();
    $user = User::factory()->create();
    grantDomainPermission($user, $invoice, 'post');

    // `post` is permitted, forcing the three-way match is not: the handler asks
    // for `forcePost` separately, so the flag cannot ride in on the post grant.
    $this->actingAs($user)
        ->postJson(domainActionUrl('post', 'invoices'), [
            'id' => $invoice->id,
            'force_three_way_match' => true,
        ])
        ->assertUnauthorized();

    expect($invoice->fresh()?->posted_at)->toBeNull();
});
