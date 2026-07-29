<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Casts\VatRegisterType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\PartyPriceRule;
use Modules\ERP\Models\PriceList;
use Modules\ERP\Models\PriceListItem;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Models\VatRegisterEntry;
use Modules\ERP\Models\VatSettlement;
use Modules\ERP\Services\Accounting\DocumentSequenceAuditService;
use Modules\ERP\Services\Accounting\VatSettlementBatchService;
use Modules\ERP\Services\Pricing\InvoiceLinePricingService;
use Modules\ERP\Services\Pricing\PriceResolverService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    DB::purge('erp-owner-secondary');
    config()->set('database.connections.erp-owner-secondary', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    Schema::connection('erp-owner-secondary')->create((new Company)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->boolean('is_deleted')->default(false);
    });
});

afterEach(function (): void {
    DB::purge('erp-owner-secondary');
});

function secondaryOwnerCompany(int $id): Company
{
    $connection = DB::connection('erp-owner-secondary');
    $connection->table((new Company)->getTable())->insert([
        'id' => $id,
        'name' => 'Secondary owner',
    ]);

    return (new Company)->setConnection('erp-owner-secondary')->newQuery()->findOrFail($id);
}

it('resolves prices exclusively through the company and item affinity connection', function (): void {
    $schema = Schema::connection('erp-owner-secondary');
    $schema->create((new Item)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('name');
        $table->string('sku');
        $table->string('uom');
        $table->string('costing_method');
        $table->unsignedInteger('taxonomy_id')->nullable();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new PriceList)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('name');
        $table->string('currency', 3);
        $table->dateTime('valid_from')->nullable();
        $table->dateTime('valid_to')->nullable();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new PriceListItem)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('price_list_id');
        $table->unsignedInteger('item_id')->nullable();
        $table->unsignedInteger('taxonomy_id')->nullable();
        $table->string('name');
        $table->string('uom')->nullable();
        $table->decimal('unit_price', 15, 4);
        $table->dateTime('valid_from')->nullable();
        $table->dateTime('valid_to')->nullable();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new PartyPriceRule)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('party_id')->nullable();
        $table->unsignedInteger('item_id')->nullable();
        $table->unsignedInteger('taxonomy_id')->nullable();
        $table->unsignedInteger('priority')->default(100);
        $table->string('discount_type');
        $table->decimal('discount_value', 15, 4);
        $table->dateTime('valid_from')->nullable();
        $table->dateTime('valid_to')->nullable();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new SalesOrder)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('party_id')->nullable();
        $table->string('currency', 3);
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new SalesOrderLine)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('sales_order_id');
        $table->unsignedInteger('item_id')->nullable();
        $table->string('name');
        $table->decimal('qty_ordered', 15, 4);
        $table->decimal('qty_invoiced', 15, 4);
        $table->decimal('unit_price', 15, 4)->nullable();
        $table->boolean('is_deleted')->default(false);
    });

    $company = secondaryOwnerCompany(9401);
    $connection = $company->getConnection();
    $connection->table((new Item)->getTable())->insert([
        'id' => 9401,
        'company_id' => 9401,
        'name' => 'Secondary item',
        'sku' => 'SECONDARY',
        'uom' => 'unit',
        'costing_method' => 'weighted_avg',
    ]);
    $connection->table((new PriceList)->getTable())->insert([
        'id' => 9401,
        'company_id' => 9401,
        'name' => 'Secondary EUR',
        'currency' => 'EUR',
    ]);
    $connection->table((new PriceListItem)->getTable())->insert([
        'id' => 9401,
        'price_list_id' => 9401,
        'item_id' => 9401,
        'name' => 'Secondary price',
        'unit_price' => '100.0000',
    ]);
    $connection->table((new PartyPriceRule)->getTable())->insert([
        'id' => 9401,
        'company_id' => 9401,
        'item_id' => 9401,
        'priority' => 1,
        'discount_type' => 'percent',
        'discount_value' => '10.0000',
    ]);
    $connection->table((new SalesOrder)->getTable())->insert([
        'id' => 9401,
        'company_id' => 9401,
        'currency' => 'EUR',
    ]);
    $connection->table((new SalesOrderLine)->getTable())->insert([
        'id' => 9401,
        'sales_order_id' => 9401,
        'item_id' => 9401,
        'name' => 'Secondary order line',
        'qty_ordered' => '3.0000',
        'qty_invoiced' => '1.0000',
        'unit_price' => '999.0000',
    ]);
    $item = (new Item)->setConnection('erp-owner-secondary')->newQuery()->findOrFail(9401);
    $default_queries = [];
    $default_connection = (string) config('database.default');
    DB::listen(static function ($query) use (&$default_queries, $default_connection): void {
        if ($query->connectionName === $default_connection
            && (str_contains($query->sql, (new PriceList)->getTable())
                || str_contains($query->sql, (new PriceListItem)->getTable())
                || str_contains($query->sql, (new PartyPriceRule)->getTable())
                || str_contains($query->sql, (new SalesOrder)->getTable())
                || str_contains($query->sql, (new SalesOrderLine)->getTable()))) {
            $default_queries[] = $query->sql;
        }
    });

    $result = app(PriceResolverService::class)->resolve($company, $item);
    $invoice_defaults = app(InvoiceLinePricingService::class)
        ->defaultsFromSalesOrderLine($company, 9401);

    expect($result->resolvedUnitPrice)->toBe('90.0000')
        ->and($result->priceListItem->getConnectionName())->toBe('erp-owner-secondary')
        ->and($invoice_defaults['unit_price'])->toBe('90.0000')
        ->and($invoice_defaults['quantity'])->toBe('2.0000')
        ->and($default_queries)->toBe([])
        ->and($connection->transactionLevel())->toBe(0);
});

it('rejects mixed pricing owners before issuing a query', function (): void {
    $company = secondaryOwnerCompany(9411);
    $item = new Item;
    $item->setConnection((string) config('database.default'));
    $item->id = 9411;
    $item->company_id = 9411;
    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    expect(fn () => app(PriceResolverService::class)->resolve($company, $item))
        ->toThrow(LogicException::class)
        ->and($queries)->toBe([])
        ->and($company->getConnection()->transactionLevel())->toBe(0);
});

it('computes VAT batches and settlements exclusively through the company affinity connection', function (): void {
    $schema = Schema::connection('erp-owner-secondary');
    $schema->create((new FiscalYear)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('year');
        $table->date('start_date');
        $table->date('end_date');
        $table->boolean('is_closed')->default(false);
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new FiscalPeriod)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('fiscal_year_id');
        $table->unsignedInteger('period_no');
        $table->date('start_date');
        $table->date('end_date');
        $table->boolean('is_closed')->default(false);
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new VatRegisterEntry)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('invoice_id');
        $table->string('register_type');
        $table->unsignedInteger('protocol_number');
        $table->date('registration_date');
        $table->unsignedInteger('fiscal_year_id');
        $table->unsignedInteger('tax_code_id');
        $table->decimal('taxable_amount', 15, 4);
        $table->decimal('tax_amount', 15, 4);
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new VatSettlement)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('fiscal_period_id');
        $table->decimal('vat_sales', 15, 4)->default(0);
        $table->decimal('vat_purchases', 15, 4)->default(0);
        $table->decimal('previous_credit', 15, 4)->default(0);
        $table->decimal('settlement_amount', 15, 4)->default(0);
        $table->string('status');
        $table->dateTime('confirmed_at')->nullable();
        $table->unsignedInteger('confirmed_by')->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
        $table->unique(['company_id', 'fiscal_period_id']);
    });

    $company = secondaryOwnerCompany(9402);
    $connection = $company->getConnection();
    $connection->table((new FiscalYear)->getTable())->insert([
        'id' => 9402,
        'company_id' => 9402,
        'year' => 2026,
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
    ]);
    $connection->table((new FiscalPeriod)->getTable())->insert([
        'id' => 9402,
        'fiscal_year_id' => 9402,
        'period_no' => 1,
        'start_date' => '2025-07-01',
        'end_date' => '2025-07-31',
    ]);
    $connection->table((new VatRegisterEntry)->getTable())->insert([
        [
            'id' => 94021,
            'company_id' => 9402,
            'invoice_id' => 1,
            'register_type' => VatRegisterType::Sales->value,
            'protocol_number' => 1,
            'registration_date' => '2025-07-15',
            'fiscal_year_id' => 9402,
            'tax_code_id' => 1,
            'taxable_amount' => '100.0000',
            'tax_amount' => '22.0000',
        ],
        [
            'id' => 94022,
            'company_id' => 9402,
            'invoice_id' => 2,
            'register_type' => VatRegisterType::Purchases->value,
            'protocol_number' => 2,
            'registration_date' => '2025-07-20',
            'fiscal_year_id' => 9402,
            'tax_code_id' => 1,
            'taxable_amount' => '40.0000',
            'tax_amount' => '8.8000',
        ],
    ]);
    $default_queries = [];
    $default_connection = (string) config('database.default');
    DB::listen(static function ($query) use (&$default_queries, $default_connection): void {
        if ($query->connectionName === $default_connection
            && (str_contains($query->sql, (new FiscalYear)->getTable())
                || str_contains($query->sql, (new FiscalPeriod)->getTable())
                || str_contains($query->sql, (new VatRegisterEntry)->getTable())
                || str_contains($query->sql, (new VatSettlement)->getTable()))) {
            $default_queries[] = $query->sql;
        }
    });

    $result = app(VatSettlementBatchService::class)->compute($company, 2026);
    $service_default_queries = $default_queries;
    $default_settlement_exists = VatSettlement::query()->where('company_id', 9402)->exists();

    expect($result['summary']['computed'])->toBe(1)
        ->and($result['periods'][0]['period'])->toBe('2026-01')
        ->and($connection->table((new VatSettlement)->getTable())->where('company_id', 9402)->value('settlement_amount'))->toBe(13.2)
        ->and($default_settlement_exists)->toBeFalse()
        ->and($service_default_queries)->toBe([])
        ->and($connection->transactionLevel())->toBe(0);
});

it('audits document sequences exclusively through the company affinity connection', function (): void {
    $schema = Schema::connection('erp-owner-secondary');
    $schema->create((new DocumentSequence)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('document_type');
        $table->unsignedInteger('fiscal_year');
        $table->unsignedInteger('last_number');
        $table->boolean('gap_allowed')->default(false);
        $table->string('prefix')->default('');
        $table->unsignedInteger('padding')->default(5);
        $table->string('format_pattern')->nullable();
        $table->string('suffix')->default('');
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new SalesOrder)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('reference')->nullable();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new PurchaseOrder)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('reference')->nullable();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new Invoice)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('direction');
        $table->string('invoice_type');
        $table->dateTime('posted_at')->nullable();
        $table->string('reference')->nullable();
        $table->boolean('is_deleted')->default(false);
    });

    $company = secondaryOwnerCompany(9403);
    $connection = $company->getConnection();
    $connection->table((new DocumentSequence)->getTable())->insert([
        'id' => 9403,
        'company_id' => 9403,
        'document_type' => DocumentType::SalesOrder->value,
        'fiscal_year' => 0,
        'last_number' => 1,
        'prefix' => 'SO-',
        'padding' => 5,
    ]);
    $connection->table((new SalesOrder)->getTable())->insert([
        'id' => 9403,
        'company_id' => 9403,
        'reference' => 'SO-00001',
    ]);
    $default_queries = [];
    $default_connection = (string) config('database.default');
    DB::listen(static function ($query) use (&$default_queries, $default_connection): void {
        if ($query->connectionName === $default_connection
            && (str_contains($query->sql, (new DocumentSequence)->getTable())
                || str_contains($query->sql, (new SalesOrder)->getTable())
                || str_contains($query->sql, (new PurchaseOrder)->getTable())
                || str_contains($query->sql, (new Invoice)->getTable()))) {
            $default_queries[] = $query->sql;
        }
    });

    $result = app(DocumentSequenceAuditService::class)->audit($company, 2026);

    expect(collect($result['checks'])->where('code', 'sequence_consistent'))->toHaveCount(1)
        ->and($result['summary']['failure'])->toBe(0)
        ->and($default_queries)->toBe([])
        ->and($connection->transactionLevel())->toBe(0);
});
