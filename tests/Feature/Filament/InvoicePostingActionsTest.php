<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Filament\Resources\Invoices\Actions\InvoicePostingActions;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Party;

uses(RefreshDatabase::class);

function invoicePostingActionPurchaseInvoice(): Invoice
{
    $company = Company::query()->create([
        'slug' => 'invoice-action-' . uniqid(),
        'name' => 'Invoice Action Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Supplier',
        'is_supplier' => true,
    ]);

    return Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Purchase,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);
}

/**
 * @return array<int, object>
 */
function invoicePostingActionFormComponents(Action $action, Invoice $invoice): array
{
    $property = new ReflectionProperty($action, 'schema');
    $schema = $property->getValue($action);
    $components = $action->evaluate($schema, typedInjections: [
        Invoice::class => $invoice,
        $invoice::class => $invoice,
    ]);

    return is_array($components) ? $components : [];
}

function grantInvoicePostingActionPermission(User $user, Invoice $invoice, string $operation): void
{
    $permission = sprintf(
        '%s.%s.%s',
        $invoice->getConnectionName() ?? 'default',
        $invoice->getTable(),
        $operation,
    );
    Permission::findOrCreate($permission, 'web');
    $user->givePermissionTo($permission);
}

it('shows the force three-way match checkbox only when the user can force post', function (): void {
    $invoice = invoicePostingActionPurchaseInvoice();
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(invoicePostingActionFormComponents(InvoicePostingActions::post(), $invoice))->toBeEmpty();

    grantInvoicePostingActionPermission($user, $invoice, 'force_post');

    expect(invoicePostingActionFormComponents(InvoicePostingActions::post(), $invoice))->toHaveCount(1);
});
