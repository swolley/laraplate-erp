<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Role;
use Modules\ERP\Casts\DeliveryNoteDirection;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Filament\Pages\BalanceSheetPage;
use Modules\ERP\Filament\Pages\BankReconciliationPage;
use Modules\ERP\Filament\Pages\IncomeStatementPage;
use Modules\ERP\Filament\Pages\SalesPipelinePage;
use Modules\ERP\Filament\Pages\StockValuationPage;
use Modules\ERP\Filament\Pages\TrialBalancePage;
use Modules\ERP\Filament\Resources\BankAccounts\BankAccountResource;
use Modules\ERP\Filament\Resources\BankStatements\BankStatementResource;
use Modules\ERP\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\ERP\Filament\Resources\Invoices\InvoiceResource;
use Modules\ERP\Filament\Resources\ReturnOrders\ReturnOrderResource;
use Modules\ERP\Filament\Resources\SupplierReturns\SupplierReturnResource;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Party;

uses(RefreshDatabase::class);

it('registers ERP Filament resource and page routes on the admin panel', function (): void {
    expect(InvoiceResource::getUrl('index'))->toContain('/admin/business/invoices')
        ->and(BankAccountResource::getUrl('index'))->toContain('/admin/business/bank-accounts')
        ->and(BankStatementResource::getUrl('index'))->toContain('/admin/business/bank-statements')
        ->and(DeliveryNoteResource::getUrl('index'))->toContain('/admin/business/delivery-notes')
        ->and(ReturnOrderResource::getUrl('index'))->toContain('/admin/business/return-orders')
        ->and(SupplierReturnResource::getUrl('index'))->toContain('/admin/business/supplier-returns')
        ->and(BankReconciliationPage::getUrl())->toEndWith('/admin/bank-reconciliation')
        ->and(SalesPipelinePage::getUrl())->toEndWith('/admin/sales-pipeline')
        ->and(StockValuationPage::getUrl())->toEndWith('/admin/stock-valuation');
});

it('renders ERP Filament smoke pages for an authenticated admin', function (): void {
    $admin = User::factory()->create([
        'email' => 'erp-filament-smoke@example.com',
        'email_verified_at' => now(),
        'password' => 'Aa1!FilamentAdminPass',
    ]);
    $admin->roles()->attach(Role::factory()->create(['name' => 'superadmin', 'guard_name' => 'web']));
    $this->actingAs($admin, 'admin');

    $company = Company::query()->create([
        'slug' => 'erp-filament-smoke',
        'name' => 'ERP Filament Smoke',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Smoke Customer',
        'is_customer' => true,
    ]);
    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
        'posted_at' => now(),
    ]);
    DeliveryNote::query()->create([
        'company_id' => $company->id,
        'direction' => DeliveryNoteDirection::Inbound,
        'reference' => 'DDT-SMOKE-IN',
    ]);

    $this->get(BankReconciliationPage::getUrl())->assertOk();
    $this->get(TrialBalancePage::getUrl())->assertOk();
    $this->get(BalanceSheetPage::getUrl())->assertOk();
    $this->get(IncomeStatementPage::getUrl())->assertOk();
    $this->get(SalesPipelinePage::getUrl())->assertOk();
    $this->get(StockValuationPage::getUrl())->assertOk();
    $this->get(BankAccountResource::getUrl('index'))->assertOk();
    $this->get(BankStatementResource::getUrl('index'))->assertOk();
    $this->get(DeliveryNoteResource::getUrl('index'))->assertOk();
    $this->get(ReturnOrderResource::getUrl('index'))->assertOk();
    $this->get(SupplierReturnResource::getUrl('index'))->assertOk();
    $this->get(InvoiceResource::getUrl('edit', ['record' => $invoice]))->assertOk();
});
