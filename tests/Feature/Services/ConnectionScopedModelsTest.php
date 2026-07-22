<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Support\ConnectionScopedModels;

beforeEach(function (): void {
    config()->set('database.connections.erp-secondary', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    Schema::connection('erp-secondary')->create((new Invoice)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->string('reference')->nullable();
    });
});

it('binds participant queries to the aggregate connection without changing the default connection', function (): void {
    $root = (new Company)->setConnection('erp-secondary');
    $models = ConnectionScopedModels::for($root);

    $models->query(Invoice::class)->getQuery()->insert(['reference' => 'secondary-only']);

    expect($root->getConnection()->table((new Invoice)->getTable())->where('reference', 'secondary-only')->exists())->toBeTrue()
        ->and(config('database.default'))->not->toBe('erp-secondary');
});

it('rejects an explicitly different participant connection before a query can write', function (): void {
    $root = (new Company)->setConnection('erp-secondary');
    $invoice = (new Invoice)->setConnection(config('database.default'));
    $models = ConnectionScopedModels::for($root, $invoice);

    expect(fn (): mixed => $models->query(Invoice::class))
        ->toThrow(LogicException::class);
});
