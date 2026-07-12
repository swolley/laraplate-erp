<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Casts\TaxKind;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\TaxCode;
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
function policyPermission(Model $record, string $operation): string
{
    return sprintf(
        '%s.%s.%s',
        $record->getConnectionName() ?? 'default',
        $record->getTable(),
        $operation,
    );
}

function policyTaxCode(Company $company): TaxCode
{
    return TaxCode::query()->create([
        'company_id' => $company->id,
        'code' => 'VAT22',
        'kind' => TaxKind::Vat->value,
        'country' => 'IT',
        'rate' => '22.0000',
        'label' => 'VAT 22%',
        'is_active' => true,
        'effective_from' => '2026-01-01',
    ]);
}

function policyDocumentSequence(Company $company): DocumentSequence
{
    return DocumentSequence::query()->create([
        'company_id' => $company->id,
        'document_type' => DocumentType::SalesInvoice,
        'fiscal_year' => 2026,
        'last_number' => 1,
        'gap_allowed' => false,
        'prefix' => 'INV-',
        'padding' => 4,
        'suffix' => '',
    ]);
}

it('allows superadmin to run ERP abilities only when state permits domain actions', function (): void {
    $invoice = policyInvoice();
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    $policy = app(ERPModelPolicy::class);

    expect($policy->post($user, $invoice))->toBeTrue()
        ->and($policy->unpost($user, $invoice))->toBeFalse()
        ->and($policy->submitEInvoice($user, $invoice))->toBeTrue()
        ->and($policy->refreshEInvoice($user, $invoice))->toBeTrue();

    $entry = JournalEntry::query()->create(['company_id' => $invoice->company_id]);
    $invoice->journal_entry_id = $entry->id;

    expect($policy->post($user, $invoice))->toBeFalse()
        ->and($policy->unpost($user, $invoice))->toBeTrue();
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

it('guards extended admin abilities with explicit permissions and model state', function (): void {
    $invoice = policyInvoice();
    $company = $invoice->company()->withoutGlobalScopes()->firstOrFail();
    $tax_code = policyTaxCode($company);
    $sequence = policyDocumentSequence($company);
    $guard = config('auth.defaults.guard');
    $guard = is_string($guard) ? $guard : 'web';
    $user = User::factory()->create();
    $policy = app(ERPModelPolicy::class);

    $permissions = [
        policyPermission($tax_code, 'supersede'),
        policyPermission($company, 'switch_context'),
        policyPermission($sequence, 'reserve'),
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, $guard);
    }

    expect($policy->supersede($user, $tax_code))->toBeFalse()
        ->and($policy->switchContext($user, $company))->toBeFalse()
        ->and($policy->reserve($user, $sequence))->toBeFalse();

    $user->givePermissionTo($permissions);

    expect($policy->supersede($user, $tax_code))->toBeTrue()
        ->and($policy->switchContext($user, $company))->toBeTrue()
        ->and($policy->reserve($user, $sequence))->toBeTrue()
        ->and($policy->supersede($user, $company))->toBeFalse()
        ->and($policy->switchContext($user, $tax_code))->toBeFalse()
        ->and($policy->reserve($user, $tax_code))->toBeFalse();
});
