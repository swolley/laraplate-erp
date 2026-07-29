<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Jobs\PublishOutboxEventJob;
use Modules\Core\Models\OutboxEvent;
use Modules\Core\Models\Version;
use Modules\Core\Models\VersionSet;
use Modules\ERP\Casts\BankStatementLineStatus;
use Modules\ERP\Casts\DeliveryNoteDirection;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Casts\PaymentRequestStatus;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Filament\Resources\PaymentRuns\Pages\CreatePaymentRun;
use Modules\ERP\Models\BankAccount;
use Modules\ERP\Models\BankStatementLine;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PaymentRequest;
use Modules\ERP\Models\ReturnOrder;
use Modules\ERP\Models\ReturnOrderLine;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\StockMovement;
use Modules\ERP\Models\SupplierReturn;
use Modules\ERP\Models\SupplierReturnLine;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Accounting\DocumentNumberAllocator;
use Modules\ERP\Services\Accounting\FiscalCalendarInstaller;
use Modules\ERP\Services\Accounting\VatSettlementService;
use Modules\ERP\Services\Banking\BankReconciliationService;
use Modules\ERP\Services\Inventory\StockMovementService;
use Modules\ERP\Services\Payments\PaymentRequestService;
use Modules\ERP\Services\Payments\PaymentRunBuilderService;
use Modules\ERP\Services\Returns\ReturnOrderService;
use Modules\ERP\Services\Returns\SupplierReturnService;
use Modules\ERP\Support\ConnectionScopedTransaction;
use Modules\ERP\Support\ErpConnectionContext;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('database.connections.erp-secondary', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $secondary_schema = Schema::connection('erp-secondary');
    $secondary_schema->create((new Company)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->boolean('is_deleted')->default(false);
    });
    $secondary_schema->create((new VersionSet)->getTable(), function (Blueprint $table): void {
        $table->id();
        $table->uuid('uuid')->unique();
        $table->string('root_type')->nullable();
        $table->string('root_id')->nullable();
        $table->string('root_connection_ref')->nullable();
        $table->string('root_table_ref')->nullable();
        $table->unsignedBigInteger(config('versionable.user_foreign_key', 'user_id'))->nullable();
        $table->string('kind');
        $table->string('reason')->nullable();
        $table->unsignedBigInteger('reverted_from_set_id')->nullable();
        $table->timestamps();
    });
    $secondary_schema->create((new Version)->getTable(), function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('version_set_id');
        $table->unsignedInteger('sequence');
        $table->string('change_type');
        $table->string('relation_path')->nullable();
        $table->json('subject_key')->nullable();
        $table->string('connection_ref')->nullable();
        $table->string('table_ref')->nullable();
        $table->unsignedBigInteger(config('versionable.user_foreign_key', 'user_id'))->nullable();
        $table->unsignedBigInteger('versionable_id');
        $table->string('versionable_type');
        $table->json('original_contents')->nullable();
        $table->json('contents')->nullable();
        $table->string('version_strategy');
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->default(false);
        $table->unique(['version_set_id', 'sequence']);
    });

    $default_schema = Schema::connection((string) config('database.default'));

    if (! $default_schema->hasTable('vend_permissions')) {
        $default_schema->create('vend_permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });
    }
});

function createSecondaryOutboxTable(): void
{
    Schema::connection('erp-secondary')->create((new OutboxEvent)->getTable(), function (Blueprint $table): void {
        $table->id();
        $table->uuid('event_id')->unique();
        $table->string('event_type');
        $table->string('aggregate_type');
        $table->string('aggregate_id');
        $table->json('payload');
        $table->timestamp('occurred_at');
        $table->timestamp('published_at')->nullable();
        $table->unsignedInteger('publish_attempts')->default(0);
        $table->text('last_error')->nullable();
        $table->timestamps();
    });
}

function createSecondaryDeliveryNoteTable(): void
{
    Schema::connection('erp-secondary')->create((new DeliveryNote)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('sales_order_id')->nullable();
        $table->string('direction');
        $table->string('reference')->nullable();
        $table->timestamp('delivered_at')->nullable();
        $table->timestamp('posted_at')->nullable();
        $table->timestamp('inventory_posted_at')->nullable();
        $table->unsignedInteger('cogs_journal_entry_id')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });
}

it('allocates document numbers only on the company affinity connection', function (): void {
    $schema = Schema::connection('erp-secondary');
    $schema->create((new DocumentSequence)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('document_type');
        $table->unsignedInteger('fiscal_year');
        $table->unsignedInteger('last_number')->default(0);
        $table->boolean('gap_allowed')->default(false);
        $table->string('prefix')->default('');
        $table->unsignedInteger('padding')->default(5);
        $table->string('format_pattern')->nullable();
        $table->string('suffix')->default('');
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
        $table->unique(['company_id', 'document_type', 'fiscal_year']);
    });
    $schema->getConnection()->table((new Company)->getTable())->insert([
        'id' => 981,
        'name' => 'Secondary allocator company',
    ]);
    $company = (new Company)->setConnection('erp-secondary');
    $company->id = 981;
    $queries = [];
    $company->getConnection()->listen(static function ($query) use (&$queries): void {
        $queries[] = $query->connectionName;
    });

    $number = app(DocumentNumberAllocator::class)->next($company, DocumentType::SalesInvoice, 2026);

    expect($number)->toBe('2026-00001')
        ->and($company->getConnection()->table((new DocumentSequence)->getTable())->where('company_id', 981)->count())->toBe(1)
        ->and(DocumentSequence::query()->where('company_id', 981)->count())->toBe(0)
        ->and($queries)->toContain('erp-secondary')
        ->and($company->getConnection()->transactionLevel())->toBe(0);
});

it('installs fiscal calendars only on the company affinity connection', function (): void {
    $schema = Schema::connection('erp-secondary');
    $schema->create((new FiscalYear)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('year');
        $table->date('start_date');
        $table->date('end_date');
        $table->boolean('is_closed')->default(false);
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
        $table->unique(['company_id', 'year']);
    });
    $schema->create((new FiscalPeriod)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('fiscal_year_id');
        $table->unsignedInteger('period_no');
        $table->date('start_date');
        $table->date('end_date');
        $table->boolean('is_closed')->default(false);
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
        $table->unique(['fiscal_year_id', 'period_no']);
    });
    $schema->getConnection()->table((new Company)->getTable())->insert([
        'id' => 982,
        'name' => 'Secondary fiscal company',
    ]);
    $company = (new Company)->setConnection('erp-secondary');
    $company->id = 982;

    $fiscalYear = app(FiscalCalendarInstaller::class)->ensureCalendarYear($company, 2031);

    expect($fiscalYear->getConnectionName())->toBe('erp-secondary')
        ->and($company->getConnection()->table((new FiscalPeriod)->getTable())->where('fiscal_year_id', $fiscalYear->getKey())->count())->toBe(12)
        ->and(FiscalYear::query()->where('company_id', 982)->count())->toBe(0)
        ->and($company->getConnection()->transactionLevel())->toBe(0);
});

it('rejects a VAT fiscal period from another connection before issuing service queries', function (): void {
    $connection = DB::connection((string) config('database.default'));
    $company_id = $connection->table((new Company)->getTable())->insertGetId([
        'slug' => 'default-vat-company',
        'name' => 'Default VAT company',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $fiscal_year_id = $connection->table((new FiscalYear)->getTable())->insertGetId([
        'company_id' => $company_id,
        'year' => 2032,
        'start_date' => '2032-01-01',
        'end_date' => '2032-12-31',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $fiscal_period_id = $connection->table((new FiscalPeriod)->getTable())->insertGetId([
        'fiscal_year_id' => $fiscal_year_id,
        'period_no' => 1,
        'start_date' => '2032-01-01',
        'end_date' => '2032-01-31',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $fiscal_period = FiscalPeriod::query()->whereKey($fiscal_period_id)->firstOrFail();
    $company = (new Company)->setConnection('erp-secondary');
    $company->id = 990;
    $company->exists = true;
    $default_queries = [];
    $secondary_queries = [];
    $connection->listen(static function ($query) use (&$default_queries): void {
        $default_queries[] = $query->sql;
    });
    DB::connection('erp-secondary')->listen(static function ($query) use (&$secondary_queries): void {
        $secondary_queries[] = $query->sql;
    });

    expect($fiscal_period->getConnection()->getName())->toBe(config('database.default'))
        ->and(fn (): array => app(VatSettlementService::class)->preview($company, $fiscal_period))
        ->toThrow(LogicException::class)
        ->and($default_queries)->toBeEmpty()
        ->and($secondary_queries)->toBeEmpty();
});

it('ignores bank statement lines only on their affinity connection', function (): void {
    $schema = Schema::connection('erp-secondary');
    $schema->create((new BankStatementLine)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->string('status');
        $table->unsignedInteger('matched_payment_id')->nullable();
        $table->unsignedInteger('difference_journal_entry_id')->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });
    $secondary = $schema->getConnection();
    $secondary->table((new BankStatementLine)->getTable())->insert([
        'id' => 983,
        'status' => BankStatementLineStatus::Imported->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $line = (new BankStatementLine)->setConnection('erp-secondary');
    $line->id = 983;
    $line->exists = true;

    app(BankReconciliationService::class)->ignore($line);

    expect($secondary->table((new BankStatementLine)->getTable())->where('id', 983)->value('status'))->toBe(BankStatementLineStatus::Ignored->value)
        ->and(BankStatementLine::query()->whereKey(983)->count())->toBe(0)
        ->and($secondary->transactionLevel())->toBe(0);
});

it('records stock movements only on the source affinity connection', function (): void {
    $schema = Schema::connection('erp-secondary');
    $schema->create((new Item)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('costing_method');
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new Warehouse)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new StockMovement)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('item_id');
        $table->unsignedInteger('warehouse_id');
        $table->string('direction');
        $table->decimal('quantity', 16, 4);
        $table->decimal('unit_cost', 16, 4)->nullable();
        $table->string('source_type')->nullable();
        $table->unsignedInteger('source_id')->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new StockLevel)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('item_id');
        $table->unsignedInteger('warehouse_id');
        $table->decimal('quantity', 16, 4);
        $table->decimal('weighted_avg_cost', 16, 4);
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
        $table->unique(['company_id', 'item_id', 'warehouse_id']);
    });
    $connection = $schema->getConnection();
    $connection->table((new Company)->getTable())->insert(['id' => 984, 'name' => 'Secondary']);
    $connection->table((new Item)->getTable())->insert(['id' => 1, 'company_id' => 984, 'costing_method' => 'weighted_avg']);
    $connection->table((new Warehouse)->getTable())->insert(['id' => 1, 'company_id' => 984]);
    $source = (new Company)->setConnection('erp-secondary');
    $source->id = 984;
    $source->exists = true;

    app(StockMovementService::class)->recordInbound(984, 1, 1, 2, '3.5000', $source);

    expect($connection->table((new StockMovement)->getTable())->where('company_id', 984)->count())->toBe(1)
        ->and($connection->table((new StockLevel)->getTable())->where('company_id', 984)->value('quantity'))->toBe(2)
        ->and(StockMovement::query()->where('company_id', 984)->count())->toBe(0)
        ->and($connection->transactionLevel())->toBe(0);
});

it('sends payment requests and applies callbacks only on their affinity connection', function (): void {
    $schema = Schema::connection('erp-secondary');
    $schema->create((new Party)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('name');
        $table->boolean('is_customer')->default(false);
        $table->boolean('is_supplier')->default(false);
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new PaymentRequest)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('party_id')->nullable();
        $table->unsignedInteger('user_id')->nullable();
        $table->unsignedInteger('partner_pool_id')->nullable();
        $table->unsignedInteger('pool_transaction_id')->nullable();
        $table->decimal('amount', 16, 4);
        $table->string('currency', 3);
        $table->date('due_on')->nullable();
        $table->string('status');
        $table->string('provider_code');
        $table->string('external_id')->nullable();
        $table->string('checkout_url')->nullable();
        $table->json('provider_payload')->nullable();
        $table->timestamp('sent_at')->nullable();
        $table->timestamp('paid_at')->nullable();
        $table->timestamp('cancelled_at')->nullable();
        $table->text('description')->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });

    $connection = $schema->getConnection();
    $connection->table((new Company)->getTable())->insert(['id' => 985, 'name' => 'Secondary payments']);
    $connection->table((new Party)->getTable())->insert([
        'id' => 985,
        'company_id' => 985,
        'name' => 'Secondary customer',
        'is_customer' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $connection->table((new PaymentRequest)->getTable())->insert([
        'id' => 985,
        'company_id' => 985,
        'party_id' => 985,
        'amount' => '42.5000',
        'currency' => 'EUR',
        'status' => PaymentRequestStatus::Draft->value,
        'provider_code' => 'stub',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $request = (new PaymentRequest)->setConnection('erp-secondary');
    $request->id = 985;
    $request->exists = true;

    $sent = app(PaymentRequestService::class)->send($request);
    config()->set('erp.model_connections', [
        (new PaymentRequest)->getTable() => 'erp-secondary',
    ]);
    config()->set('erp.payment_requests.providers.stub.callback_api_key', 'secondary-callback-secret');

    $this->withToken('secondary-callback-secret')
        ->postJson('/api/v1/erp/payment-requests/stub/callbacks', [
            'external_id' => $sent->external_id,
            'status' => 'paid',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', PaymentRequestStatus::Paid->value);

    expect($sent->getConnectionName())->toBe('erp-secondary')
        ->and($connection->table((new PaymentRequest)->getTable())->where('id', 985)->value('status'))->toBe(PaymentRequestStatus::Paid->value)
        ->and(PaymentRequest::query()->whereKey(985)->count())->toBe(0)
        ->and($connection->transactionLevel())->toBe(0);
});

it('approves customer returns only on their affinity connection', function (): void {
    $schema = Schema::connection('erp-secondary');
    $schema->create((new Party)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('name');
        $table->boolean('is_customer')->default(false);
        $table->boolean('is_supplier')->default(false);
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new ReturnOrder)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('party_id');
        $table->unsignedInteger('invoice_id')->nullable();
        $table->unsignedInteger('credit_note_invoice_id')->nullable();
        $table->unsignedInteger('delivery_note_id')->nullable();
        $table->string('reference')->nullable();
        $table->string('status');
        $table->timestamp('processed_at')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });

    $connection = $schema->getConnection();
    $connection->table((new Company)->getTable())->insert(['id' => 986, 'name' => 'Secondary returns']);
    $connection->table((new Party)->getTable())->insert([
        'id' => 986,
        'company_id' => 986,
        'name' => 'Secondary return customer',
        'is_customer' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $connection->table((new ReturnOrder)->getTable())->insert([
        'id' => 986,
        'company_id' => 986,
        'party_id' => 986,
        'status' => ReturnStatus::Draft->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $return_order = (new ReturnOrder)->setConnection('erp-secondary');
    $return_order->id = 986;
    $return_order->exists = true;

    $approved = app(ReturnOrderService::class)->approve($return_order);

    expect($approved->getConnectionName())->toBe('erp-secondary')
        ->and($connection->table((new ReturnOrder)->getTable())->where('id', 986)->value('status'))->toBe(ReturnStatus::Approved->value)
        ->and(ReturnOrder::query()->whereKey(986)->count())->toBe(0)
        ->and($connection->transactionLevel())->toBe(0);
});

it('completes customer returns with their outbox event only on the affinity connection', function (): void {
    Queue::fake();
    createSecondaryOutboxTable();
    createSecondaryDeliveryNoteTable();
    $schema = Schema::connection('erp-secondary');
    $schema->create((new ReturnOrder)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('party_id');
        $table->unsignedInteger('invoice_id')->nullable();
        $table->unsignedInteger('credit_note_invoice_id')->nullable();
        $table->unsignedInteger('delivery_note_id')->nullable();
        $table->string('reference')->nullable();
        $table->string('status');
        $table->timestamp('processed_at')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new ReturnOrderLine)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('return_order_id');
        $table->unsignedInteger('invoice_line_id')->nullable();
        $table->unsignedInteger('delivery_note_line_id')->nullable();
        $table->unsignedInteger('item_id');
        $table->unsignedInteger('warehouse_id');
        $table->decimal('quantity', 16, 4);
        $table->decimal('unit_cost', 16, 4)->nullable();
        $table->decimal('unit_price', 16, 4)->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });

    $connection = $schema->getConnection();
    $connection->table((new Company)->getTable())->insert(['id' => 987, 'name' => 'Secondary customer return']);
    $connection->table((new DeliveryNote)->getTable())->insert([
        'id' => 987,
        'company_id' => 987,
        'direction' => DeliveryNoteDirection::Inbound->value,
        'posted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $connection->table((new ReturnOrder)->getTable())->insert([
        'id' => 987,
        'company_id' => 987,
        'party_id' => 987,
        'delivery_note_id' => 987,
        'status' => ReturnStatus::Approved->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $connection->table((new ReturnOrderLine)->getTable())->insert([
        'id' => 987,
        'company_id' => 987,
        'return_order_id' => 987,
        'item_id' => 987,
        'warehouse_id' => 987,
        'quantity' => '1.0000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $return_order = (new ReturnOrder)
        ->setConnection('erp-secondary')
        ->newQuery()
        ->findOrFail(987);

    $processed = app(ReturnOrderService::class)->complete($return_order);

    expect($processed->getConnectionName())->toBe('erp-secondary')
        ->and($connection->table((new ReturnOrder)->getTable())->where('id', 987)->value('status'))->toBe(ReturnStatus::Processed->value)
        ->and($connection->table((new OutboxEvent)->getTable())->where('event_type', 'erp.customer-return.completed')->count())->toBe(1)
        ->and(OutboxEvent::query()->where('event_type', 'erp.customer-return.completed')->exists())->toBeFalse()
        ->and($connection->transactionLevel())->toBe(0);
    Queue::assertPushed(
        PublishOutboxEventJob::class,
        fn (PublishOutboxEventJob $job): bool => $job->connectionName === 'erp-secondary',
    );
});

it('completes supplier returns with their outbox event only on the affinity connection', function (): void {
    Queue::fake();
    createSecondaryOutboxTable();
    createSecondaryDeliveryNoteTable();
    $schema = Schema::connection('erp-secondary');
    $schema->create((new SupplierReturn)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('party_id');
        $table->unsignedInteger('purchase_order_id')->nullable();
        $table->unsignedInteger('debit_note_invoice_id')->nullable();
        $table->unsignedInteger('delivery_note_id')->nullable();
        $table->string('reference')->nullable();
        $table->string('status');
        $table->timestamp('processed_at')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new SupplierReturnLine)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('supplier_return_id');
        $table->unsignedInteger('invoice_line_id')->nullable();
        $table->unsignedInteger('purchase_order_line_id')->nullable();
        $table->unsignedInteger('goods_receipt_line_id')->nullable();
        $table->unsignedInteger('delivery_note_line_id')->nullable();
        $table->unsignedInteger('item_id');
        $table->unsignedInteger('warehouse_id');
        $table->decimal('quantity', 16, 4);
        $table->decimal('unit_price', 16, 4)->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });

    $connection = $schema->getConnection();
    $connection->table((new Company)->getTable())->insert(['id' => 988, 'name' => 'Secondary supplier return']);
    $connection->table((new DeliveryNote)->getTable())->insert([
        'id' => 988,
        'company_id' => 988,
        'direction' => DeliveryNoteDirection::Outbound->value,
        'posted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $connection->table((new SupplierReturn)->getTable())->insert([
        'id' => 988,
        'company_id' => 988,
        'party_id' => 988,
        'delivery_note_id' => 988,
        'status' => ReturnStatus::Approved->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $connection->table((new SupplierReturnLine)->getTable())->insert([
        'id' => 988,
        'company_id' => 988,
        'supplier_return_id' => 988,
        'item_id' => 988,
        'warehouse_id' => 988,
        'quantity' => '1.0000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $supplier_return = (new SupplierReturn)
        ->setConnection('erp-secondary')
        ->newQuery()
        ->findOrFail(988);

    $processed = app(SupplierReturnService::class)->complete($supplier_return);

    expect($processed->getConnectionName())->toBe('erp-secondary')
        ->and($connection->table((new SupplierReturn)->getTable())->where('id', 988)->value('status'))->toBe(ReturnStatus::Processed->value)
        ->and($connection->table((new OutboxEvent)->getTable())->where('event_type', 'erp.supplier-return.completed')->count())->toBe(1)
        ->and(OutboxEvent::query()->where('event_type', 'erp.supplier-return.completed')->exists())->toBeFalse()
        ->and($connection->transactionLevel())->toBe(0);
    Queue::assertPushed(
        PublishOutboxEventJob::class,
        fn (PublishOutboxEventJob $job): bool => $job->connectionName === 'erp-secondary',
    );
});

it('requires trusted model prototypes before id based payment lookups', function (): void {
    $callback_source = (new ReflectionMethod(PaymentRequestService::class, 'applyCallback'))
        ->getParameters()[2];
    $payment_run_source = (new ReflectionMethod(PaymentRunBuilderService::class, 'build'))
        ->getParameters()[4];

    expect($callback_source->isOptional())->toBeFalse()
        ->and($payment_run_source->isOptional())->toBeFalse();
});

it('rejects a mismatched participant before any aggregate write', function (): void {
    $root = (new Company)->setConnection('erp-secondary');
    $participant = (new Invoice)->setConnection((string) config('database.default'));
    $connection = $root->getConnection();
    $callback_ran = false;

    expect(function () use ($root, $participant, $connection, &$callback_ran): void {
        ConnectionScopedTransaction::run(
            $root,
            function () use ($connection, &$callback_ran): void {
                $callback_ran = true;
                $connection->table((new Company)->getTable())->insert([
                    'id' => 989,
                    'name' => 'Must not be written',
                ]);
            },
            $participant,
        );
    })->toThrow(LogicException::class)
        ->and($callback_ran)->toBeFalse()
        ->and($connection->table((new Company)->getTable())->where('id', 989)->exists())->toBeFalse()
        ->and($connection->transactionLevel())->toBe(0);
});

it('resolves trusted model connections for runtime entrypoints', function (): void {
    config()->set('erp.model_connections', [
        (new PaymentRequest)->getTable() => 'erp-secondary',
        BankAccount::class => 'erp-secondary',
    ]);

    $context = app(ErpConnectionContext::class);
    $payment_request = $context->model(PaymentRequest::class);
    $page = (new ReflectionClass(CreatePaymentRun::class))->newInstanceWithoutConstructor();
    $page_source = (new ReflectionMethod(CreatePaymentRun::class, 'bankAccountSource'))->invoke($page);

    expect($payment_request->getConnectionName())->toBe('erp-secondary')
        ->and($page_source)->toBeInstanceOf(BankAccount::class)
        ->and($page_source->getConnectionName())->toBe('erp-secondary');
});

it('rejects unconfigured database connections in trusted model context', function (): void {
    config()->set('erp.model_connections', [
        PaymentRequest::class => 'missing-erp-connection',
    ]);

    expect(fn () => app(ErpConnectionContext::class)->model(PaymentRequest::class))
        ->toThrow(LogicException::class);
});

it('preserves the model connection when trusted context has no override', function (): void {
    config()->set('erp.model_connections', []);
    $prototype = (new PaymentRequest)->setConnection('erp-secondary');

    $resolved = app(ErpConnectionContext::class)->resolve($prototype);

    expect($resolved)->toBe($prototype)
        ->and($resolved->getConnectionName())->toBe('erp-secondary');
});
