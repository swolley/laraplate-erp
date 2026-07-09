<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Modules\ERP\Filament\Resources\Accounts\AccountResource;
use Modules\ERP\Filament\Resources\BankAccounts\BankAccountResource;
use Modules\ERP\Filament\Resources\BankStatements\BankStatementResource;
use Modules\ERP\Filament\Resources\Companies\CompanyResource;
use Modules\ERP\Filament\Resources\Contacts\ContactResource;
use Modules\ERP\Filament\Resources\DeliveryNotes\Actions\DeliveryNotePostingActions;
use Modules\ERP\Filament\Resources\DeliveryNotes\DeliveryNoteResource;
use Modules\ERP\Filament\Resources\DocumentSequences\DocumentSequenceResource;
use Modules\ERP\Filament\Resources\FiscalPeriods\Actions\FiscalPeriodActions;
use Modules\ERP\Filament\Resources\FiscalPeriods\FiscalPeriodResource;
use Modules\ERP\Filament\Resources\FiscalYears\Actions\FiscalYearActions;
use Modules\ERP\Filament\Resources\FiscalYears\FiscalYearResource;
use Modules\ERP\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use Modules\ERP\Filament\Resources\Items\ItemResource;
use Modules\ERP\Filament\Resources\JournalEntries\Actions\JournalEntryActions;
use Modules\ERP\Filament\Resources\JournalEntries\JournalEntryResource;
use Modules\ERP\Filament\Resources\Leads\LeadResource;
use Modules\ERP\Filament\Resources\Opportunities\OpportunityResource;
use Modules\ERP\Filament\Resources\Parties\PartyResource;
use Modules\ERP\Filament\Resources\Payments\PaymentResource;
use Modules\ERP\Filament\Resources\PaymentTerms\PaymentTermResource;
use Modules\ERP\Filament\Resources\Projects\ProjectResource;
use Modules\ERP\Filament\Resources\Quotations\QuotationResource;
use Modules\ERP\Filament\Resources\ReturnOrders\ReturnOrderResource;
use Modules\ERP\Filament\Resources\SalesOrders\Actions\SalesOrderAmendmentActions;
use Modules\ERP\Filament\Resources\StockLevels\StockLevelResource;
use Modules\ERP\Filament\Resources\SupplierReturns\SupplierReturnResource;
use Modules\ERP\Filament\Resources\TaxCodes\TaxCodeResource;
use Modules\ERP\Filament\Resources\VatRegister\VatRegisterResource;
use Modules\ERP\Filament\Resources\VatSettlements\VatSettlementResource;
use Modules\ERP\Filament\Resources\Warehouses\WarehouseResource;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\TaxCode;

uses(RefreshDatabase::class);

it('registers Filament pages for companies', function (): void {
    $pages = CompanyResource::getPages();

    expect($pages)
        ->toHaveKey('index')
        ->and($pages)->toHaveKey('create')
        ->and($pages)->toHaveKey('edit');
});

it('binds company resource to Company model', function (): void {
    expect(CompanyResource::getModel())->toBe(Company::class);
});

it('defines delivery note post and unpost actions', function (): void {
    expect(DeliveryNotePostingActions::post()->getName())->toBe('post')
        ->and(DeliveryNotePostingActions::unpost()->getName())->toBe('unpost');
});

it('defines sales order amendment action', function (): void {
    expect(SalesOrderAmendmentActions::amend()->getName())->toBe('amend');
});

it('registers Filament pages for tax codes', function (): void {
    $pages = TaxCodeResource::getPages();

    expect($pages)
        ->toHaveKey('index')
        ->and($pages)->toHaveKey('create')
        ->and($pages)->toHaveKey('edit');
});

it('binds tax code resource to TaxCode model', function (): void {
    expect(TaxCodeResource::getModel())->toBe(TaxCode::class);
});

it('registers Filament pages for accounts', function (): void {
    expect(AccountResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(AccountResource::getModel())->toBe(Account::class);
});

it('registers Filament pages for journal entries including view', function (): void {
    expect(JournalEntryResource::getPages())->toHaveKeys(['index', 'create', 'view', 'edit'])
        ->and(JournalEntryResource::getModel())->toBe(JournalEntry::class);
});

it('defines journal entry reverse action', function (): void {
    expect(JournalEntryActions::reverse()->getName())->toBe('reverse');
});

it('disallows editing posted journal entries via resource gate', function (): void {
    $draft = new JournalEntry(['posted_at' => null]);
    $posted = new JournalEntry(['posted_at' => now()]);

    expect(JournalEntryResource::canEdit($draft))->toBeTrue()
        ->and(JournalEntryResource::canEdit($posted))->toBeFalse();
});

it('registers Filament pages for fiscal years', function (): void {
    expect(FiscalYearResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(FiscalYearResource::getModel())->toBe(FiscalYear::class);
});

it('defines fiscal year close action', function (): void {
    expect(FiscalYearActions::close()->getName())->toBe('close_year');
});

it('registers Filament pages for fiscal periods', function (): void {
    expect(FiscalPeriodResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(FiscalPeriodResource::getModel())->toBe(FiscalPeriod::class);
});

it('defines fiscal period close and reopen actions', function (): void {
    expect(FiscalPeriodActions::close()->getName())->toBe('close_period')
        ->and(FiscalPeriodActions::reopen()->getName())->toBe('reopen_period');
});

it('registers Filament pages for document sequences', function (): void {
    expect(DocumentSequenceResource::getPages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(DocumentSequenceResource::getModel())->toBe(DocumentSequence::class);
});

it('configures erp resource forms and tables without throwing', function (): void {
    $resources = [
        CompanyResource::class,
        WarehouseResource::class,
        StockLevelResource::class,
        ItemResource::class,
        GoodsReceiptResource::class,
        DeliveryNoteResource::class,
        SupplierReturnResource::class,
        ReturnOrderResource::class,
        BankAccountResource::class,
        AccountResource::class,
        BankStatementResource::class,
        ContactResource::class,
        DocumentSequenceResource::class,
        FiscalPeriodResource::class,
        FiscalYearResource::class,
        LeadResource::class,
        OpportunityResource::class,
        PartyResource::class,
        PaymentResource::class,
        PaymentTermResource::class,
        ProjectResource::class,
        QuotationResource::class,
        TaxCodeResource::class,
        VatRegisterResource::class,
        VatSettlementResource::class,
    ];

    $livewire = $this->createStub(HasTable::class);

    foreach ($resources as $resource_class) {
        expect($resource_class::form(Schema::make()))->toBeInstanceOf(Schema::class);

        $table = Table::make($livewire);
        $table->query(fn () => $resource_class::getModel()::query());
        $resource_class::table($table);

        expect($table->getQuery())->not->toBeNull();
    }
});
