<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Models\Company;
use Modules\ERP\Support\ConnectionScopedTransaction;

beforeEach(function (): void {
    config()->set('database.connections.erp-secondary', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    Schema::connection('erp-secondary')->create((new Company)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
    });
});

it('runs the mutation and transaction on the aggregate connection', function (): void {
    $company = (new Company)->setConnection('erp-secondary');
    $connection = $company->getConnection();
    $events = [];

    $connection->listen(function () use (&$events): void {
        $events[] = 'query';
    });

    ConnectionScopedTransaction::run($company, function () use ($company): void {
        $company->getConnection()->table($company->getTable())->insert(['name' => 'Secondary company']);
    });

    expect($connection->table($company->getTable())->where('name', 'Secondary company')->exists())->toBeTrue()
        ->and($events)->not->toBeEmpty();
});

it('rejects mixed aggregate connections before the transaction callback runs', function (): void {
    $primary = new Company;
    $secondary = (new Company)->setConnection('erp-secondary');
    $callback_ran = false;

    expect(function () use ($primary, $secondary, &$callback_ran): void {
        ConnectionScopedTransaction::run($primary, function () use (&$callback_ran): void {
            $callback_ran = true;
        }, $secondary);
    })->toThrow(LogicException::class)
        ->and($callback_ran)->toBeFalse();
});
