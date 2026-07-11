<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Casts\CrudExecutor;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\StockMovementDirection;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\GoodsReceiptLine;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\ReturnOrderLine;
use Modules\ERP\Models\StockCostLayer;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\StockMovement;
use Modules\ERP\Models\SupplierReturnLine;
use Modules\ERP\Models\Warehouse;

it('rejects negative operational quantities through model rules', function (Model $model, string $field): void {
    $model->forceFill([$field => '-0.0001']);

    try {
        $model->validateWithRules(CrudExecutor::INSERT);
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);

        return;
    }

    $this->fail($model::class . ' accepted a negative ' . $field . '.');
})->with([
    'delivery note line quantity' => [new DeliveryNoteLine, 'quantity'],
    'goods receipt line quantity' => [new GoodsReceiptLine, 'quantity'],
    'invoice line returned quantity' => [new InvoiceLine, 'qty_returned'],
    'return order line quantity' => [new ReturnOrderLine, 'quantity'],
    'stock cost layer remaining quantity' => [new StockCostLayer, 'qty_remaining'],
    'stock level quantity' => [new StockLevel, 'quantity'],
    'supplier return line quantity' => [new SupplierReturnLine, 'quantity'],
]);

it('rejects negative return line override prices through model rules', function (Model $model): void {
    $model->forceFill(['unit_price' => '-0.0001']);

    try {
        $model->validateWithRules(CrudExecutor::INSERT);
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('unit_price');

        return;
    }

    $this->fail($model::class . ' accepted a negative unit_price.');
})->with([
    'customer return line unit price' => [new ReturnOrderLine],
    'supplier return line unit price' => [new SupplierReturnLine],
]);

it('rejects non-positive stock movement quantities through model rules', function (): void {
    $movement = new StockMovement;
    $movement->forceFill([
        'direction' => StockMovementDirection::In,
        'quantity' => '0.0000',
        'unit_cost' => '1.0000',
    ]);

    try {
        $movement->validateWithRules(CrudExecutor::INSERT);
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('quantity');

        return;
    }

    $this->fail('StockMovement accepted a zero quantity.');
});

it('enforces positive stock movement quantities at database level when supported', function (): void {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite cannot add table check constraints after Schema::create in these migrations.');
    }

    $company = Company::query()->create([
        'slug' => 'db-check',
        'name' => 'DB Check',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'Main',
        'code' => 'MAIN',
    ]);
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Checked item',
        'sku' => 'CHK-1',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    $movement = new StockMovement([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'direction' => StockMovementDirection::In,
        'quantity' => '0.0000',
        'unit_cost' => '1.0000',
    ]);
    $movement->setSkipValidation();

    expect(fn () => $movement->save())->toThrow(QueryException::class);
});
