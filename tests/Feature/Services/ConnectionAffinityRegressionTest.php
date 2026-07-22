<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\BankStatementLineStatus;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Models\BankStatementLine;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\StockMovement;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Accounting\DocumentNumberAllocator;
use Modules\ERP\Services\Accounting\FiscalCalendarInstaller;
use Modules\ERP\Services\Banking\BankReconciliationService;
use Modules\ERP\Services\Inventory\StockMovementService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('database.connections.erp-secondary', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);
    if (! Schema::hasTable('vend_permissions')) {
        Schema::create('vend_permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });
    }
});

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
        $table->unique(['fiscal_year_id', 'period_no']);
    });
    $company = (new Company)->setConnection('erp-secondary');
    $company->id = 982;

    $fiscalYear = app(FiscalCalendarInstaller::class)->ensureCalendarYear($company, 2031);

    expect($fiscalYear->getConnectionName())->toBe('erp-secondary')
        ->and($company->getConnection()->table((new FiscalPeriod)->getTable())->where('fiscal_year_id', $fiscalYear->getKey())->count())->toBe(12)
        ->and(FiscalYear::query()->where('company_id', 982)->count())->toBe(0)
        ->and($company->getConnection()->transactionLevel())->toBe(0);
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
    $schema->create((new Company)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new Item)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('costing_method');
    });
    $schema->create((new Warehouse)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
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
