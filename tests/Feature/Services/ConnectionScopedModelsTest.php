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

    expect(fn (): ConnectionScopedModels => ConnectionScopedModels::for($root, $invoice))
        ->toThrow(LogicException::class);
});

it('rejects an existing default participant with an inherited connection before creating the scope', function (): void {
    $default_participant = Invoice::withoutEvents(
        fn (): Invoice => (new Invoice)->newFromBuilder([
            'id' => 991,
            'reference' => 'default-participant',
        ]),
    );
    $secondary_participant = (new Invoice)->setConnection('erp-secondary');
    $root = (new Company)->setConnection('erp-secondary');

    expect($default_participant->getConnectionName())->toBeNull()
        ->and($default_participant->exists)->toBeTrue()
        ->and($default_participant->getConnection()->getName())->toBe(config('database.default'))
        ->and(fn (): ConnectionScopedModels => ConnectionScopedModels::for(
            $root,
            $secondary_participant,
            $default_participant,
        ))
        ->toThrow(LogicException::class);
});

it('allows an unsaved participant prototype to inherit the aggregate connection', function (): void {
    $root = (new Company)->setConnection('erp-secondary');
    $models = ConnectionScopedModels::for($root, new Invoice);

    expect($models->model(Invoice::class)->getConnectionName())->toBe('erp-secondary');
});
