<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Modules\ERP\Filament\Pages\BankReconciliationPage;
use Modules\ERP\Filament\Resources\BankAccounts\BankAccountResource;
use Modules\ERP\Filament\Resources\BankStatements\BankStatementResource;
use Modules\ERP\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\ERP\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use Modules\ERP\Filament\Resources\Invoices\InvoiceResource;
use Modules\ERP\Filament\Resources\Items\ItemResource;
use Modules\ERP\Filament\Resources\Contacts\ContactResource;
use Modules\ERP\Filament\Resources\Parties\PartyResource;
use Modules\ERP\Filament\Resources\Parties\RelationManagers\PriceRulesRelationManager;
use Modules\ERP\Filament\Resources\Leads\LeadResource;
use Modules\ERP\Filament\Resources\Opportunities\OpportunityResource;
use Modules\ERP\Filament\Resources\Projects\ProjectResource;
use Modules\ERP\Filament\Resources\PriceLists\PriceListResource;
use Modules\ERP\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Modules\ERP\Filament\Resources\Quotations\QuotationResource;
use Modules\ERP\Filament\Resources\ReturnOrders\ReturnOrderResource;
use Modules\ERP\Filament\Resources\SalesOrders\SalesOrderResource;
use Modules\ERP\Filament\Resources\StockLevels\StockLevelResource;
use Modules\ERP\Filament\Resources\SupplierReturns\SupplierReturnResource;
use Modules\ERP\Filament\Resources\Warehouses\WarehouseResource;

it('defines Filament pages for party resource', function (): void {
    expect(PartyResource::getPages())
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('edit');
});

it('party resource form includes core fields', function (): void {
    $schema = PartyResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('company_id')
        ->and($names)->toContain('name')
        ->and($names)->toContain('is_customer')
        ->and($names)->toContain('is_supplier')
        ->and($names)->toContain('is_active');
});

it('registers party price-rule relation manager', function (): void {
    expect(PartyResource::getRelations())->toContain(PriceRulesRelationManager::class);
});

it('party price-rule relation manager exposes pricing fields', function (): void {
    $manager = new PriceRulesRelationManager;
    $schema = $manager->form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('item_id')
        ->and($names)->toContain('taxonomy_id')
        ->and($names)->toContain('discount_type')
        ->and($names)->toContain('discount_value')
        ->and($names)->toContain('priority')
        ->and($names)->toContain('valid_from')
        ->and($names)->toContain('valid_to')
        ->and($manager->table(Table::make($this->createStub(HasTable::class))))->toBeInstanceOf(Table::class);
});

it('defines Filament pages for price list resource', function (): void {
    expect(PriceListResource::getPages())
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('edit');
});

it('price list resource form includes list header and item repeater fields', function (): void {
    $schema = PriceListResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('company_id')
        ->and($names)->toContain('name')
        ->and($names)->toContain('currency')
        ->and($names)->toContain('valid_from')
        ->and($names)->toContain('valid_to')
        ->and($names)->toContain('price_list_items');
});

it('defines Filament pages for contact resource', function (): void {
    expect(ContactResource::getPages())
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('edit');
});

it('contact resource form includes party linkage field', function (): void {
    $schema = ContactResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('company_id')
        ->and($names)->toContain('party_ids');
});

it('defines Filament pages for quotation resource', function (): void {
    expect(QuotationResource::getPages())
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('edit');
});

it('quotation resource form includes line items repeater', function (): void {
    $schema = QuotationResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('line_items')
        ->and($names)->toContain('party_id')
        ->and($names)->toContain('opportunity_id');
});

it('defines Filament pages for bank reconciliation resources', function (): void {
    expect(BankAccountResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(BankStatementResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(class_exists(BankReconciliationPage::class))->toBeTrue()
        ->and(method_exists(BankReconciliationPage::class, 'suggestedPaymentsForLine'))->toBeTrue();
});

it('bank account and statement forms include core fields', function (): void {
    $bank_account_schema = BankAccountResource::form(new Schema());
    $bank_account_names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $bank_account_schema->getComponents(),
    );

    $bank_statement_schema = BankStatementResource::form(new Schema());
    $bank_statement_names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $bank_statement_schema->getComponents(),
    );

    expect($bank_account_names)->toContain('company_id')
        ->and($bank_account_names)->toContain('name')
        ->and($bank_statement_names)->toContain('bank_account_id')
        ->and($bank_statement_names)->toContain('period_start');
});

it('defines Filament pages for return resources', function (): void {
    expect(ReturnOrderResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(SupplierReturnResource::getPages())->toHaveKeys(['index', 'create', 'edit']);
});

it('return forms include line repeaters', function (): void {
    $return_schema = ReturnOrderResource::form(new Schema());
    $return_names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $return_schema->getComponents(),
    );

    $supplier_schema = SupplierReturnResource::form(new Schema());
    $supplier_names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $supplier_schema->getComponents(),
    );

    expect($return_names)->toContain('lines')
        ->and($supplier_names)->toContain('lines');
});

it('defines Filament pages for lead resource', function (): void {
    expect(LeadResource::getPages())
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('edit');
});

it('defines Filament pages for opportunity resource', function (): void {
    expect(OpportunityResource::getPages())
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('edit');
});

it('defines Filament pages for sales order resource', function (): void {
    expect(SalesOrderResource::getPages())
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('edit');
});

it('sales order resource form includes line items repeater', function (): void {
    $schema = SalesOrderResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('line_items')
        ->and($names)->toContain('party_id');
});

it('defines Filament pages for project resource', function (): void {
    expect(ProjectResource::getPages())
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('edit');
});

it('project resource form includes validity fields', function (): void {
    $schema = ProjectResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('valid_from')
        ->and($names)->toContain('valid_to');
});

it('defines Filament pages for foundation resources', function (): void {
    expect(ItemResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(WarehouseResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(StockLevelResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(DeliveryNoteResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(InvoiceResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(PurchaseOrderResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(GoodsReceiptResource::getPages())->toHaveKeys(['index', 'create', 'edit']);
});

it('item resource form includes core fields', function (): void {
    $schema = ItemResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('name')
        ->and($names)->toContain('sku');
});

it('warehouse resource form includes core fields', function (): void {
    $schema = WarehouseResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('name')
        ->and($names)->toContain('code');
});

it('stock level resource form includes core fields', function (): void {
    $schema = StockLevelResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('item_id')
        ->and($names)->toContain('warehouse_id');
});

it('delivery note resource form includes core fields', function (): void {
    $schema = DeliveryNoteResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('sales_order_id')
        ->and($names)->toContain('direction')
        ->and($names)->toContain('delivered_at')
        ->and($names)->toContain('posted_at')
        ->and($names)->toContain('line_items');
});

it('invoice resource form includes core fields', function (): void {
    $schema = InvoiceResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('direction')
        ->and($names)->toContain('party_id')
        ->and($names)->toContain('currency');
});

it('purchase order resource form includes core fields', function (): void {
    $schema = PurchaseOrderResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('party_id')
        ->and($names)->toContain('status')
        ->and($names)->toContain('line_items');
});

it('goods receipt resource form includes core fields', function (): void {
    $schema = GoodsReceiptResource::form(new Schema());
    $names = array_map(
        static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('purchase_order_id')
        ->and($names)->toContain('received_at')
        ->and($names)->toContain('posted_at')
        ->and($names)->toContain('line_items');
});
