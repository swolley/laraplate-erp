<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use Modules\ERP\Filament\Resources\Invoices\Actions\InvoicePostingActions;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;

uses(RefreshDatabase::class);

function invoicePostingActionPurchaseInvoice(): Invoice
{
    // `purchase()` puts a supplier in the invoice's own company, which is the rule the model
    // enforces and the reason this used to take three hand-built records.
    return Invoice::factory()
        ->for(Company::factory()->create(['name' => 'Invoice Action Co']))
        ->purchase()
        ->create();
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
    $permission = PermissionName::forModel($invoice, $operation);
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
