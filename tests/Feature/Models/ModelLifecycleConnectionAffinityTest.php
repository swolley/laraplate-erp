<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\QuoteStatus;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Opportunity;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Models\Quotation;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('database.connections.erp-model-secondary', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $schema = Schema::connection('erp-model-secondary');

    $schema->create((new Company)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->string('slug')->nullable();
        $table->string('name');
        $table->string('fiscal_country', 2)->nullable();
        $table->string('default_currency', 3)->nullable();
        $table->boolean('is_default')->default(false);
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new Opportunity)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('party_id')->nullable();
        $table->string('status')->nullable();
        $table->timestamp('won_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new Quotation)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('party_id')->nullable();
        $table->unsignedInteger('opportunity_id')->nullable();
        $table->string('currency', 3);
        $table->string('status');
        $table->unsignedTinyInteger('version')->default(0);
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new SalesOrder)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('party_id')->nullable();
        $table->unsignedInteger('quotation_id')->nullable();
        $table->unsignedInteger('project_id')->nullable();
        $table->unsignedInteger('amends_sales_order_id')->nullable();
        $table->string('currency', 3);
        $table->string('status');
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new PurchaseOrder)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new Item)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new PurchaseOrderLine)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('purchase_order_id');
        $table->unsignedInteger('item_id')->nullable();
        $table->string('name');
        $table->decimal('qty_ordered', 18, 4);
        $table->decimal('qty_received', 18, 4)->default(0);
        $table->decimal('qty_returned', 18, 4)->default(0);
        $table->decimal('unit_price', 18, 4)->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new SalesOrderLine)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('sales_order_id');
        $table->unsignedInteger('quotation_item_id')->nullable();
        $table->unsignedInteger('item_id')->nullable();
        $table->string('name');
        $table->decimal('qty_ordered', 18, 4);
        $table->decimal('qty_delivered', 18, 4)->default(0);
        $table->decimal('qty_invoiced', 18, 4)->default(0);
        $table->decimal('qty_returned', 18, 4)->default(0);
        $table->decimal('unit_price', 18, 4)->nullable();
        $table->string('status');
        $table->timestamp('locked_at')->nullable();
        $table->unsignedBigInteger('locked_user_id')->nullable();
        $table->timestamp('locked_until')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new DeliveryNoteLine)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('delivery_note_id');
        $table->unsignedInteger('item_id');
        $table->unsignedInteger('warehouse_id');
        $table->decimal('quantity', 18, 4);
        $table->unsignedInteger('sales_order_line_id')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->default(false);
    });

    Quotation::disableVersioning();
    SalesOrder::disableVersioning();
    PurchaseOrderLine::disableVersioning();
    DeliveryNoteLine::disableVersioning();
    Company::disableVersioning();
});

afterEach(function (): void {
    Quotation::enableVersioning();
    SalesOrder::enableVersioning();
    PurchaseOrderLine::enableVersioning();
    DeliveryNoteLine::enableVersioning();
    Company::enableVersioning();
});

it('validates a quotation opportunity on the quotation connection', function (): void {
    $connection = Schema::connection('erp-model-secondary')->getConnection();
    $connection->table((new Opportunity)->getTable())->insert([
        'id' => 9101,
        'company_id' => 71,
        'party_id' => null,
    ]);

    $quotation = (new Quotation)->setConnection('erp-model-secondary');
    $quotation->forceFill([
        'company_id' => 71,
        'party_id' => null,
        'opportunity_id' => 9101,
        'currency' => 'EUR',
        'status' => QuoteStatus::Draft,
    ]);
    $quotation->setSkipValidation();
    $quotation->save();

    expect($quotation->getConnectionName())->toBe('erp-model-secondary')
        ->and($connection->table($quotation->getTable())->where('id', $quotation->getKey())->exists())->toBeTrue()
        ->and(Quotation::query()->whereKey($quotation->getKey())->exists())->toBeFalse();
});

it('validates a sales order quotation on the sales order connection', function (): void {
    $connection = Schema::connection('erp-model-secondary')->getConnection();
    $connection->table((new Quotation)->getTable())->insert([
        'id' => 9102,
        'company_id' => 72,
        'party_id' => null,
        'currency' => 'EUR',
        'status' => QuoteStatus::Draft->value,
    ]);

    $order = (new SalesOrder)->setConnection('erp-model-secondary');
    $order->forceFill([
        'company_id' => 72,
        'party_id' => null,
        'quotation_id' => 9102,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::Draft,
    ]);
    $order->setSkipValidation();
    $order->save();

    expect($order->getConnectionName())->toBe('erp-model-secondary')
        ->and($connection->table($order->getTable())->where('id', $order->getKey())->exists())->toBeTrue()
        ->and(SalesOrder::query()->whereKey($order->getKey())->exists())->toBeFalse();
});

it('rejects a purchase order line item from another company on its own connection', function (): void {
    $connection = Schema::connection('erp-model-secondary')->getConnection();
    $connection->table((new PurchaseOrder)->getTable())->insert([
        'id' => 9103,
        'company_id' => 73,
    ]);
    $connection->table((new Item)->getTable())->insert([
        'id' => 9104,
        'company_id' => 74,
    ]);

    $line = (new PurchaseOrderLine)->setConnection('erp-model-secondary');
    $line->forceFill([
        'purchase_order_id' => 9103,
        'item_id' => 9104,
        'name' => 'Cross-company item',
        'qty_ordered' => 1,
        'qty_received' => 0,
        'qty_returned' => 0,
    ]);
    $line->setSkipValidation();

    expect(fn () => $line->save())->toThrow(
        ValidationException::class,
        'The item must belong to the same company as the purchase order.',
    )
        ->and($connection->table($line->getTable())->count())->toBe(0);
});

it('reuses a loaded purchase order on the line connection without querying it again', function (): void {
    $connection = Schema::connection('erp-model-secondary')->getConnection();
    $connection->table((new Item)->getTable())->insert([
        'id' => 9109,
        'company_id' => 79,
    ]);
    $purchase_order = (new PurchaseOrder)->setConnection('erp-model-secondary');
    $purchase_order->setRawAttributes([
        'id' => 9110,
        'company_id' => 79,
    ], true);
    $purchase_order->exists = true;

    $line = (new PurchaseOrderLine)->setConnection('erp-model-secondary');
    $line->forceFill([
        'purchase_order_id' => 9110,
        'item_id' => 9109,
        'name' => 'Loaded purchase order',
        'qty_ordered' => 1,
        'qty_received' => 0,
        'qty_returned' => 0,
    ]);
    $line->setRelation('purchase_order', $purchase_order);
    $line->setSkipValidation();
    $connection->flushQueryLog();
    $connection->enableQueryLog();

    $line->save();

    $purchase_order_queries = collect($connection->getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], $purchase_order->getTable()));
    $connection->disableQueryLog();

    expect($purchase_order_queries)->toBeEmpty()
        ->and($connection->table($line->getTable())->where('id', $line->getKey())->exists())->toBeTrue();
});

it('rejects a purchase order line with a loaded purchase order on another connection before insert', function (): void {
    $secondary = Schema::connection('erp-model-secondary')->getConnection();
    $secondary_count = $secondary->table((new PurchaseOrderLine)->getTable())->count();
    $primary_count = PurchaseOrderLine::query()->withoutGlobalScopes()->count();
    $purchase_order = (new PurchaseOrder)->setConnection((string) config('database.default'));
    $purchase_order->id = 9111;
    $purchase_order->exists = true;

    $line = (new PurchaseOrderLine)->setConnection('erp-model-secondary');
    $line->forceFill([
        'purchase_order_id' => 9111,
        'item_id' => 1,
        'name' => 'Mismatched purchase order',
        'qty_ordered' => 1,
        'qty_received' => 0,
        'qty_returned' => 0,
    ]);
    $line->setRelation('purchase_order', $purchase_order);
    $line->setSkipValidation();

    expect(fn () => $line->save())->toThrow(
        LogicException::class,
        'ERP participants must use the aggregate database connection.',
    )
        ->and($secondary->table($line->getTable())->count())->toBe($secondary_count)
        ->and(PurchaseOrderLine::query()->withoutGlobalScopes()->count())->toBe($primary_count);
});

it('locks a delivery note sales order line on the delivery line connection', function (): void {
    $connection = Schema::connection('erp-model-secondary')->getConnection();
    $connection->table((new SalesOrder)->getTable())->insert([
        'id' => 1,
        'company_id' => 75,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::Draft->value,
    ]);
    $connection->table((new SalesOrderLine)->getTable())->insert([
        'id' => 9105,
        'sales_order_id' => 1,
        'name' => 'Secondary sales line',
        'qty_ordered' => 1,
        'status' => SalesOrderLineStatus::Open->value,
    ]);

    $line = (new DeliveryNoteLine)->setConnection('erp-model-secondary');
    $line->forceFill([
        'company_id' => 75,
        'delivery_note_id' => 1,
        'item_id' => 1,
        'warehouse_id' => 1,
        'quantity' => 1,
        'sales_order_line_id' => 9105,
    ]);
    $line->setSkipValidation();
    $line->save();

    expect($connection->table((new SalesOrderLine)->getTable())->where('id', 9105)->value('locked_at'))
        ->not->toBeNull()
        ->and(SalesOrderLine::query()->whereKey(9105)->exists())->toBeFalse();
});

it('rejects a delivery note line with a loaded sales order line on another connection before insert', function (): void {
    $secondary = Schema::connection('erp-model-secondary')->getConnection();
    $primary_count = DeliveryNoteLine::query()->withoutGlobalScopes()->count();
    $secondary_count = $secondary->table((new DeliveryNoteLine)->getTable())->count();
    $mismatched_sales_order_line = (new SalesOrderLine)->setConnection((string) config('database.default'));
    $mismatched_sales_order_line->id = 9108;
    $mismatched_sales_order_line->exists = true;

    $line = (new DeliveryNoteLine)->setConnection('erp-model-secondary');
    $line->forceFill([
        'company_id' => 76,
        'delivery_note_id' => 1,
        'item_id' => 1,
        'warehouse_id' => 1,
        'quantity' => 1,
        'sales_order_line_id' => 9108,
    ]);
    $line->setRelation('sales_order_line', $mismatched_sales_order_line);
    $line->setSkipValidation();

    expect(fn () => $line->save())->toThrow(
        LogicException::class,
        'ERP participants must use the aggregate database connection.',
    )
        ->and(DeliveryNoteLine::query()->withoutGlobalScopes()->count())->toBe($primary_count)
        ->and($secondary->table($line->getTable())->count())->toBe($secondary_count);
});

it('does not mutate a stale loaded delivery sales order line while resolving the matching line', function (): void {
    $secondary = Schema::connection('erp-model-secondary')->getConnection();
    $secondary->table((new SalesOrder)->getTable())->insert([
        'id' => 2,
        'company_id' => 77,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::Draft->value,
    ]);
    $secondary->table((new SalesOrderLine)->getTable())->insert([
        'id' => 9112,
        'sales_order_id' => 2,
        'name' => 'Matching sales line',
        'qty_ordered' => 1,
        'status' => SalesOrderLineStatus::Open->value,
    ]);
    $stale_sales_order_line = new SalesOrderLine;
    $stale_sales_order_line->id = 9113;
    $stale_sales_order_line->exists = true;

    $line = (new DeliveryNoteLine)->setConnection('erp-model-secondary');
    $line->forceFill([
        'company_id' => 77,
        'delivery_note_id' => 1,
        'item_id' => 1,
        'warehouse_id' => 1,
        'quantity' => 1,
        'sales_order_line_id' => 9112,
    ]);
    $line->setRelation('sales_order_line', $stale_sales_order_line);
    $line->setSkipValidation();
    $line->save();

    expect($stale_sales_order_line->getConnectionName())->toBeNull()
        ->and($secondary->table((new SalesOrderLine)->getTable())->where('id', 9112)->value('locked_at'))
        ->not->toBeNull();
});

it('enforces a single default company on the company connection', function (): void {
    $primary_default_count = Company::query()->withoutGlobalScopes()->where('is_default', true)->count();
    $connection = Schema::connection('erp-model-secondary')->getConnection();
    $connection->table((new Company)->getTable())->insert([
        [
            'id' => 9106,
            'name' => 'Previous default',
            'is_default' => true,
        ],
        [
            'id' => 9107,
            'name' => 'New default',
            'is_default' => false,
        ],
    ]);

    $company = (new Company)->setConnection('erp-model-secondary');
    $company->setRawAttributes([
        'id' => 9107,
        'name' => 'New default',
        'is_default' => false,
    ], true);
    $company->exists = true;
    $company->is_default = true;
    $company->setSkipValidation();
    $company->save();

    expect($connection->table($company->getTable())->where('id', 9106)->value('is_default'))->toBe(0)
        ->and($connection->table($company->getTable())->where('id', 9107)->value('is_default'))->toBe(1)
        ->and(Company::query()->withoutGlobalScopes()->where('is_default', true)->count())->toBe($primary_default_count);
});
