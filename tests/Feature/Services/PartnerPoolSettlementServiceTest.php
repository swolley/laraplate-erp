<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\User;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Casts\MovementType;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Movement;
use Modules\ERP\Models\MovementAllocation;
use Modules\ERP\Models\PartnerPool;
use Modules\ERP\Services\Cash\PartnerPoolSettlementService;

uses(RefreshDatabase::class);

/** @return array{PartnerPool, Movement, User, User, User} */
function partnerPoolFixture(): array
{
    $company = Company::query()->create([
        'slug' => 'partner-pool-' . uniqid(),
        'name' => 'Partner Pool Company',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $expense = Account::query()->create([
        'company_id' => $company->id,
        'code' => '5700',
        'name' => 'Shared expenses',
        'kind' => AccountKind::Expense,
        'is_active' => true,
    ]);
    $users = User::factory()->count(3)->create();
    $pool = PartnerPool::query()->create([
        'company_id' => $company->id,
        'name' => 'Partners',
        'currency' => 'EUR',
    ]);
    $pool->members()->attach($users->modelKeys());
    $movement = Movement::query()->create([
        'company_id' => $company->id,
        'type' => MovementType::Expense,
        'occurred_on' => '2026-07-21',
        'amount_doc' => '90.0000',
        'currency_doc' => 'EUR',
        'counterparty_account_id' => $expense->id,
        'description' => 'Shared dinner',
    ]);

    return [$pool, $movement, $users[0], $users[1], $users[2]];
}

it('derives balances and records suggested settlements without a mutable balance', function (): void {
    [$pool, $movement, $alice, $bob, $carol] = partnerPoolFixture();
    $service = app(PartnerPoolSettlementService::class);

    $service->allocate($movement, $pool, [
        (int) $alice->id => ['owed' => '30.0000', 'paid' => '90.0000'],
        (int) $bob->id => ['owed' => '30.0000', 'paid' => '0.0000'],
        (int) $carol->id => ['owed' => '30.0000', 'paid' => '0.0000'],
    ]);

    expect($service->balances($pool))->toBe([
        (int) $alice->id => '60.0000',
        (int) $bob->id => '-30.0000',
        (int) $carol->id => '-30.0000',
    ])->and($service->suggestSettleUp($pool))->toBe([
        ['from_user_id' => (int) $bob->id, 'to_user_id' => (int) $alice->id, 'amount' => '30.0000', 'currency' => 'EUR'],
        ['from_user_id' => (int) $carol->id, 'to_user_id' => (int) $alice->id, 'amount' => '30.0000', 'currency' => 'EUR'],
    ]);

    $service->settle($pool, (int) $bob->id, (int) $alice->id, '30.0000', 'Bank transfer');

    expect($service->balances($pool))->toBe([
        (int) $alice->id => '30.0000',
        (int) $bob->id => '0.0000',
        (int) $carol->id => '-30.0000',
    ])->and($service->suggestSettleUp($pool))->toHaveCount(1);
});

it('rejects an unbalanced split atomically', function (): void {
    [$pool, $movement, $alice, $bob] = partnerPoolFixture();
    $service = app(PartnerPoolSettlementService::class);

    expect(fn () => $service->allocate($movement, $pool, [
        (int) $alice->id => ['owed' => '45.0000', 'paid' => '80.0000'],
        (int) $bob->id => ['owed' => '45.0000', 'paid' => '0.0000'],
    ]))->toThrow(ValidationException::class)
        ->and(MovementAllocation::query()->count())->toBe(0);
});

it('rejects settlements that exceed the current balances', function (): void {
    [$pool, $movement, $alice, $bob] = partnerPoolFixture();
    $service = app(PartnerPoolSettlementService::class);
    $service->allocate($movement, $pool, [
        (int) $alice->id => ['owed' => '45.0000', 'paid' => '90.0000'],
        (int) $bob->id => ['owed' => '45.0000', 'paid' => '0.0000'],
    ]);

    expect(fn () => $service->settle($pool, (int) $bob->id, (int) $alice->id, '50.0000'))
        ->toThrow(ValidationException::class);
});
