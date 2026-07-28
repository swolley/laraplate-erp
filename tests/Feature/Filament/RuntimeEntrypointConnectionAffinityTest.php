<?php

declare(strict_types=1);

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema as FilamentSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Casts\BankStatementLineStatus;
use Modules\ERP\Casts\PaymentDirection;
use Modules\ERP\Casts\PurchaseOrderStatus;
use Modules\ERP\Filament\Pages\BankReconciliationPage;
use Modules\ERP\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use Modules\ERP\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use Modules\ERP\Filament\Resources\SalesOrders\Pages\CreateSalesOrder;
use Modules\ERP\Filament\Resources\SalesOrders\Schemas\SalesOrderForm;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\BankStatementLine;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\Payment;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\Quotation;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Services\Diagnostics\ErpHealthCheckService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('database.connections.erp-entrypoint-secondary', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);
});

it('runs ERP health checks on the trusted company connection', function (): void {
    $schema = Schema::connection('erp-entrypoint-secondary');
    $schema->create((new Company)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->boolean('is_default')->default(false);
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new Account)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('kind');
        $table->boolean('is_active')->default(true);
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new FiscalYear)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('year');
        $table->date('start_date');
        $table->date('end_date');
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new FiscalPeriod)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('fiscal_year_id');
        $table->unsignedInteger('period_no');
        $table->date('start_date');
        $table->date('end_date');
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new DocumentSequence)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('document_type');
        $table->unsignedInteger('fiscal_year');
        $table->boolean('is_deleted')->default(false);
    });

    $connection = $schema->getConnection();
    $connection->table((new Company)->getTable())->insert([
        'id' => 9701,
        'name' => 'Secondary health company',
        'is_default' => true,
    ]);
    $connection->table((new Account)->getTable())->insert([
        'id' => 9701,
        'company_id' => 9701,
        'kind' => AccountKind::Asset->value,
    ]);
    $connection->table((new FiscalYear)->getTable())->insert([
        'id' => 9701,
        'company_id' => 9701,
        'year' => now()->year,
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->endOfYear()->toDateString(),
    ]);
    $connection->table((new FiscalPeriod)->getTable())->insert([
        'id' => 9701,
        'fiscal_year_id' => 9701,
        'period_no' => now()->month,
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
    ]);
    $connection->table((new DocumentSequence)->getTable())->insert([
        'id' => 9701,
        'company_id' => 9701,
        'document_type' => 'sales_invoice',
        'fiscal_year' => now()->year,
    ]);
    config()->set('erp.model_connections', [
        Company::class => 'erp-entrypoint-secondary',
    ]);

    $default_company_count = Company::query()->withoutGlobalScopes()->count();
    $result = app(ErpHealthCheckService::class)->run();
    $checks = collect($result['checks'])->keyBy('key');

    expect($checks->get('default_company')['status'])->toBe('success')
        ->and($checks->get('chart_of_accounts')['status'])->toBe('success')
        ->and($checks->get('fiscal_calendar')['status'])->toBe('success')
        ->and($checks->get('document_sequences')['status'])->toBe('success')
        ->and(Company::query()->withoutGlobalScopes()->count())->toBe($default_company_count);
});

it('creates purchase orders only on the company affinity connection', function (): void {
    $schema = Schema::connection('erp-entrypoint-secondary');
    $schema->create((new Company)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new Party)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('name');
        $table->boolean('is_supplier')->default(false);
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new PurchaseOrder)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('party_id');
        $table->string('reference')->nullable();
        $table->string('currency', 3);
        $table->string('status');
        $table->timestamp('ordered_at')->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });

    $connection = $schema->getConnection();
    $connection->table((new Company)->getTable())->insert([
        'id' => 9702,
        'name' => 'Secondary purchase company',
    ]);
    $connection->table((new Party)->getTable())->insert([
        'id' => 9702,
        'company_id' => 9702,
        'name' => 'Secondary supplier',
        'is_supplier' => true,
    ]);
    config()->set('erp.model_connections', [
        Company::class => 'erp-entrypoint-secondary',
    ]);

    $method = new ReflectionMethod(CreatePurchaseOrder::class, 'handleRecordCreation');
    $record = $method->invoke(new CreatePurchaseOrder(), [
        'company_id' => 9702,
        'party_id' => 9702,
        'reference' => 'PO-SECONDARY',
        'currency' => 'EUR',
        'status' => PurchaseOrderStatus::Draft->value,
        'line_items' => [],
    ]);

    expect($record)->toBeInstanceOf(PurchaseOrder::class)
        ->and($record->getConnectionName())->toBe('erp-entrypoint-secondary')
        ->and($connection->table((new PurchaseOrder)->getTable())->where('reference', 'PO-SECONDARY')->count())->toBe(1)
        ->and(PurchaseOrder::query()->where('reference', 'PO-SECONDARY')->count())->toBe(0);
});

it('calculates bank reconciliation differences from the selected line connection', function (): void {
    $schema = Schema::connection('erp-entrypoint-secondary');
    $schema->create((new BankStatementLine)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->decimal('amount_doc', 16, 4);
        $table->string('currency_doc', 3);
        $table->string('status');
        $table->unsignedInteger('matched_payment_id')->nullable();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new Payment)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('direction');
        $table->decimal('amount_doc', 16, 4);
        $table->string('currency_doc', 3);
        $table->boolean('is_deleted')->default(false);
    });

    $connection = $schema->getConnection();
    $connection->table((new BankStatementLine)->getTable())->insert([
        'id' => 9703,
        'company_id' => 9703,
        'amount_doc' => '105.0000',
        'currency_doc' => 'EUR',
        'status' => BankStatementLineStatus::Imported->value,
    ]);
    $connection->table((new Payment)->getTable())->insert([
        'id' => 9703,
        'company_id' => 9703,
        'direction' => PaymentDirection::Inbound->value,
        'amount_doc' => '100.0000',
        'currency_doc' => 'EUR',
    ]);
    config()->set('erp.model_connections', [
        BankStatementLine::class => 'erp-entrypoint-secondary',
    ]);

    $page = new BankReconciliationPage();
    $page->data = [
        'bank_statement_line_id' => 9703,
        'payment_id' => 9703,
    ];

    expect($page->currentDifferenceAmount())->toBe('5.0000')
        ->and(BankStatementLine::query()->whereKey(9703)->count())->toBe(0)
        ->and(Payment::query()->whereKey(9703)->count())->toBe(0);
});

it('does not offer bank payments or accounts before a statement line is selected', function (): void {
    $company = Company::query()->create([
        'slug' => 'bank-options-default',
        'name' => 'Default bank options company',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Default bank options customer',
        'is_customer' => true,
    ]);
    Account::query()->create([
        'company_id' => $company->id,
        'code' => '5999',
        'name' => 'Default expense',
        'kind' => AccountKind::Expense,
        'is_active' => true,
    ]);
    Payment::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => PaymentDirection::Inbound,
        'payment_date' => now()->toDateString(),
        'amount_doc' => '10.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '10.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
    ]);

    $page = new BankReconciliationPage();
    $payment_options = new ReflectionMethod($page, 'paymentOptions');
    $account_options = new ReflectionMethod($page, 'expenseAccountOptions');

    expect($payment_options->invoke($page))->toBe([])
        ->and($account_options->invoke($page))->toBe([]);
});

it('loads purchase order company party and item options from the company affinity connection', function (): void {
    $schema = Schema::connection('erp-entrypoint-secondary');
    createEntrypointOrderLookupTables($schema);
    seedEntrypointOrderLookups($schema, 9704, is_supplier: true);
    config()->set('erp.model_connections', [
        Company::class => 'erp-entrypoint-secondary',
    ]);

    $page = new CreatePurchaseOrder();
    $page->data = [
        'company_id' => 9704,
        'line_items' => [['item_id' => null]],
    ];
    $form = PurchaseOrderForm::configure(
        FilamentSchema::make($page)
            ->model(PurchaseOrder::class)
            ->statePath('data'),
    );
    $form->fill($page->data);

    $company = $form->getComponentByStatePath('company_id');
    $party = $form->getComponentByStatePath('party_id');
    $repeater = $form->getComponentByStatePath('line_items');
    $item = $repeater instanceof Repeater
        ? collect($repeater->getItems())->first()?->getComponent('item_id')
        : null;

    expect($company)->toBeInstanceOf(Select::class)
        ->and($party)->toBeInstanceOf(Select::class)
        ->and($repeater)->toBeInstanceOf(Repeater::class)
        ->and($item)->toBeInstanceOf(Select::class)
        ->and($company->getSearchResults('Secondary'))->toHaveKey(9704)
        ->and($party->getSearchResults('Supplier'))->toHaveKey(9704)
        ->and($item->getSearchResults('Item'))->toHaveKey(9704);
});

it('loads sales order company party and item options from the company affinity connection', function (): void {
    $schema = Schema::connection('erp-entrypoint-secondary');
    createEntrypointOrderLookupTables($schema);
    $schema->create((new Quotation)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('party_id');
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new SalesOrder)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('reference')->nullable();
        $table->boolean('is_deleted')->default(false);
    });
    seedEntrypointOrderLookups($schema, 9705, is_customer: true);
    $connection = $schema->getConnection();
    $connection->table((new SalesOrder)->getTable())->insert([
        [
            'id' => 9705,
            'company_id' => 9705,
            'reference' => 'SO-CURRENT',
        ],
        [
            'id' => 9706,
            'company_id' => 9705,
            'reference' => 'SO-OTHER',
        ],
    ]);
    config()->set('erp.model_connections', [
        Company::class => 'erp-entrypoint-secondary',
    ]);

    $page = new CreateSalesOrder();
    $page->data = [
        'company_id' => 9705,
        'line_items' => [['item_id' => null]],
    ];
    $record = (new SalesOrder())->setConnection('erp-entrypoint-secondary');
    $record->forceFill(['id' => 9705, 'company_id' => 9705, 'reference' => 'SO-CURRENT']);
    $record->exists = true;
    $form = SalesOrderForm::configure(
        FilamentSchema::make($page)
            ->model($record)
            ->statePath('data'),
    );
    $form->fill($page->data);

    $company = $form->getComponentByStatePath('company_id');
    $party = $form->getComponentByStatePath('party_id');
    $repeater = $form->getComponentByStatePath('line_items');
    $item = $repeater instanceof Repeater
        ? collect($repeater->getItems())->first()?->getComponent('item_id')
        : null;
    $quotation = $form->getComponentByStatePath('quotation_id');
    $amends = $form->getComponentByStatePath('amends_sales_order_id');
    $queries = [];
    $connection->listen(static function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    expect($company)->toBeInstanceOf(Select::class)
        ->and($party)->toBeInstanceOf(Select::class)
        ->and($repeater)->toBeInstanceOf(Repeater::class)
        ->and($item)->toBeInstanceOf(Select::class)
        ->and($quotation)->toBeInstanceOf(Select::class)
        ->and($amends)->toBeInstanceOf(Select::class)
        ->and($company->getSearchResults('Secondary'))->toHaveKey(9705)
        ->and($party->getSearchResults('Customer'))->toHaveKey(9705)
        ->and($item->getSearchResults('Item'))->toHaveKey(9705);

    $queries = [];

    expect($quotation->getSearchResults('not-an-id'))->toBe([])
        ->and($queries)->toBe([])
        ->and($amends->getOptions())->not->toHaveKey(9705)
        ->and($amends->getOptions())->toHaveKey(9706);
});

function createEntrypointOrderLookupTables(Illuminate\Database\Schema\Builder $schema): void
{
    $schema->create((new Company)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new Party)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('name');
        $table->boolean('is_customer')->default(false);
        $table->boolean('is_supplier')->default(false);
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new Item)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('name');
        $table->string('sku');
        $table->boolean('is_deleted')->default(false);
    });
}

function seedEntrypointOrderLookups(
    Illuminate\Database\Schema\Builder $schema,
    int $id,
    bool $is_customer = false,
    bool $is_supplier = false,
): void {
    $connection = $schema->getConnection();
    $connection->table((new Company)->getTable())->insert([
        'id' => $id,
        'name' => 'Secondary Company ' . $id,
    ]);
    $connection->table((new Party)->getTable())->insert([
        'id' => $id,
        'company_id' => $id,
        'name' => ($is_supplier ? 'Secondary Supplier ' : 'Secondary Customer ') . $id,
        'is_customer' => $is_customer,
        'is_supplier' => $is_supplier,
    ]);
    $connection->table((new Item)->getTable())->insert([
        'id' => $id,
        'company_id' => $id,
        'name' => 'Secondary Item ' . $id,
        'sku' => 'SEC-' . $id,
    ]);
}
