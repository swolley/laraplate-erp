<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\RecordOrigin;
use Modules\Core\Models\User;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Casts\MovementType;
use Modules\ERP\Import\Data\ExternalExpenseAllocationInput;
use Modules\ERP\Import\Enums\ImportMutation;
use Modules\ERP\Import\Exceptions\ExternalIdentityConflict;
use Modules\ERP\Import\Exceptions\PostedImportConflict;
use Modules\ERP\Import\Services\ExternalExpenseAllocationService;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Movement;
use Modules\ERP\Models\MovementAllocation;
use Modules\ERP\Models\PartnerPool;
use Modules\ERP\Services\Cash\MovementPostingService;

uses(RefreshDatabase::class);

/** @return array{PartnerPool, Movement, User, User, User} */
function externalExpenseAllocationFixture(): array
{
    $company = Company::query()->create([
        'slug' => 'external-allocation-' . uniqid(),
        'name' => 'External expense allocation',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    Account::query()->create([
        'company_id' => $company->id,
        'code' => '1103',
        'name' => 'Bank',
        'kind' => AccountKind::Asset,
        'meta' => ['erp_role' => 'bank_cash'],
        'is_active' => true,
    ]);
    $expense = Account::query()->create([
        'company_id' => $company->id,
        'code' => '5603',
        'name' => 'Shared expense',
        'kind' => AccountKind::Expense,
        'is_active' => true,
    ]);
    $users = User::factory()->count(3)->create();
    $pool = PartnerPool::query()->create([
        'company_id' => $company->id,
        'name' => 'Studio partners',
        'currency' => 'EUR',
    ]);
    $pool->members()->attach($users->modelKeys());
    $movement = Movement::query()->create([
        'company_id' => $company->id,
        'type' => MovementType::Expense,
        'occurred_on' => '2022-12-03',
        'amount_doc' => '90.0000',
        'currency_doc' => 'EUR',
        'counterparty_account_id' => $expense->id,
        'description' => 'Legacy shared expense',
    ]);

    return [$pool, $movement, $users[0], $users[1], $users[2]];
}

function externalExpenseAllocationInput(array $overrides = []): ExternalExpenseAllocationInput
{
    return new ExternalExpenseAllocationInput(...array_replace([
        'movementId' => 1,
        'partnerPoolId' => 1,
        'shares' => [
            1 => ['owed' => '45.0000', 'paid' => '90.0000'],
            2 => ['owed' => '45.0000', 'paid' => '0.0000'],
        ],
        'sourceKey' => 'legacy_symfony:nebula',
        'externalId' => 'movement-allocation:42',
        'fingerprint' => hash('sha256', 'fixture allocation 42'),
        'sourceUpdatedAt' => CarbonImmutable::parse('2022-12-03T00:00:00+01:00'),
    ], $overrides));
}

it('normalizes immutable allocation shares and rejects non-string money', function (): void {
    $input = externalExpenseAllocationInput([
        'shares' => [
            10 => ['owed' => '45', 'paid' => '90'],
            20 => ['owed' => '45.0', 'paid' => '0'],
        ],
    ]);

    expect($input->shares)->toBe([
        10 => ['owed' => '45.0000', 'paid' => '90.0000'],
        20 => ['owed' => '45.0000', 'paid' => '0.0000'],
    ])->and(fn () => externalExpenseAllocationInput([
        'shares' => [1 => ['owed' => 45.0, 'paid' => '90.0000']],
    ]))->toThrow(InvalidArgumentException::class);
});

it('creates expense allocations and records their source identity', function (): void {
    [$pool, $movement, $alice, $bob] = externalExpenseAllocationFixture();
    $input = externalExpenseAllocationInput([
        'movementId' => (int) $movement->id,
        'partnerPoolId' => (int) $pool->id,
        'shares' => [
            (int) $alice->id => ['owed' => '45.0000', 'paid' => '90.0000'],
            (int) $bob->id => ['owed' => '45.0000', 'paid' => '0.0000'],
        ],
    ]);

    $mutation = app(ExternalExpenseAllocationService::class)->ingest($input);
    $origin = RecordOrigin::query()->sole();

    expect($mutation)->toBe(ImportMutation::Created)
        ->and(MovementAllocation::query()->count())->toBe(2)
        ->and(MovementAllocation::query()->sum('owed_amount'))->toEqual(90.0)
        ->and(MovementAllocation::query()->sum('paid_amount'))->toEqual(90.0)
        ->and($origin->referable_id)->toBe((int) $movement->id)
        ->and($origin->external_id)->toBe('movement-allocation:42')
        ->and($origin->fingerprint)->toBe($input->identity()->fingerprint);
});

it('skips an unchanged allocation without replacing its rows', function (): void {
    [$pool, $movement, $alice, $bob] = externalExpenseAllocationFixture();
    $input = externalExpenseAllocationInput([
        'movementId' => (int) $movement->id,
        'partnerPoolId' => (int) $pool->id,
        'shares' => [
            (int) $alice->id => ['owed' => '45.0000', 'paid' => '90.0000'],
            (int) $bob->id => ['owed' => '45.0000', 'paid' => '0.0000'],
        ],
    ]);
    $service = app(ExternalExpenseAllocationService::class);
    $service->ingest($input);
    $ids = MovementAllocation::query()->orderBy('id')->pluck('id')->all();

    expect($service->ingest($input))->toBe(ImportMutation::Skipped)
        ->and(MovementAllocation::query()->orderBy('id')->pluck('id')->all())->toBe($ids)
        ->and(RecordOrigin::query()->count())->toBe(1);
});

it('atomically replaces changed unposted allocations', function (): void {
    [$pool, $movement, $alice, $bob, $carol] = externalExpenseAllocationFixture();
    $base = [
        'movementId' => (int) $movement->id,
        'partnerPoolId' => (int) $pool->id,
        'shares' => [
            (int) $alice->id => ['owed' => '45.0000', 'paid' => '90.0000'],
            (int) $bob->id => ['owed' => '45.0000', 'paid' => '0.0000'],
        ],
    ];
    $service = app(ExternalExpenseAllocationService::class);
    $service->ingest(externalExpenseAllocationInput($base));
    $changed = externalExpenseAllocationInput([
        ...$base,
        'shares' => [
            (int) $alice->id => ['owed' => '30.0000', 'paid' => '0.0000'],
            (int) $bob->id => ['owed' => '30.0000', 'paid' => '90.0000'],
            (int) $carol->id => ['owed' => '30.0000', 'paid' => '0.0000'],
        ],
        'fingerprint' => hash('sha256', 'changed fixture allocation 42'),
    ]);

    expect($service->ingest($changed))->toBe(ImportMutation::Updated)
        ->and(MovementAllocation::query()->count())->toBe(3)
        ->and((string) MovementAllocation::query()->where('user_id', $bob->id)->sole()->paid_amount)->toBe('90.0000')
        ->and(RecordOrigin::query()->sole()->fingerprint)->toBe($changed->identity()->fingerprint);
});

it('rolls back a changed allocation when replacement validation fails', function (): void {
    [$pool, $movement, $alice, $bob] = externalExpenseAllocationFixture();
    $base = [
        'movementId' => (int) $movement->id,
        'partnerPoolId' => (int) $pool->id,
        'shares' => [
            (int) $alice->id => ['owed' => '45.0000', 'paid' => '90.0000'],
            (int) $bob->id => ['owed' => '45.0000', 'paid' => '0.0000'],
        ],
    ];
    $service = app(ExternalExpenseAllocationService::class);
    $service->ingest(externalExpenseAllocationInput($base));
    $original_ids = MovementAllocation::query()->orderBy('id')->pluck('id')->all();
    $original_fingerprint = RecordOrigin::query()->sole()->fingerprint;

    expect(fn () => $service->ingest(externalExpenseAllocationInput([
        ...$base,
        'shares' => [
            (int) $alice->id => ['owed' => '40.0000', 'paid' => '90.0000'],
            (int) $bob->id => ['owed' => '40.0000', 'paid' => '0.0000'],
        ],
        'fingerprint' => hash('sha256', 'invalid changed allocation 42'),
    ])))->toThrow(ValidationException::class)
        ->and(MovementAllocation::query()->orderBy('id')->pluck('id')->all())->toBe($original_ids)
        ->and(RecordOrigin::query()->sole()->fingerprint)->toBe($original_fingerprint);
});

it('rejects changed allocations after the expense movement is posted', function (): void {
    [$pool, $movement, $alice, $bob] = externalExpenseAllocationFixture();
    $base = [
        'movementId' => (int) $movement->id,
        'partnerPoolId' => (int) $pool->id,
        'shares' => [
            (int) $alice->id => ['owed' => '45.0000', 'paid' => '90.0000'],
            (int) $bob->id => ['owed' => '45.0000', 'paid' => '0.0000'],
        ],
    ];
    $service = app(ExternalExpenseAllocationService::class);
    $service->ingest(externalExpenseAllocationInput($base));
    app(MovementPostingService::class)->post($movement);

    expect(fn () => $service->ingest(externalExpenseAllocationInput([
        ...$base,
        'fingerprint' => hash('sha256', 'changed posted allocation 42'),
    ])))->toThrow(PostedImportConflict::class)
        ->and(MovementAllocation::query()->count())->toBe(2);
});

it('rejects reusing an allocation identity for another movement', function (): void {
    [$pool, $movement, $alice, $bob] = externalExpenseAllocationFixture();
    $base = [
        'movementId' => (int) $movement->id,
        'partnerPoolId' => (int) $pool->id,
        'shares' => [
            (int) $alice->id => ['owed' => '45.0000', 'paid' => '90.0000'],
            (int) $bob->id => ['owed' => '45.0000', 'paid' => '0.0000'],
        ],
    ];
    $service = app(ExternalExpenseAllocationService::class);
    $service->ingest(externalExpenseAllocationInput($base));
    $other_movement = Movement::query()->create([
        'company_id' => $movement->company_id,
        'type' => MovementType::Expense,
        'occurred_on' => '2022-12-04',
        'amount_doc' => '90.0000',
        'currency_doc' => 'EUR',
        'counterparty_account_id' => $movement->counterparty_account_id,
    ]);

    expect(fn () => $service->ingest(externalExpenseAllocationInput([
        ...$base,
        'movementId' => (int) $other_movement->id,
        'fingerprint' => hash('sha256', 'same identity on another movement'),
    ])))->toThrow(ExternalIdentityConflict::class)
        ->and(MovementAllocation::query()->where('movement_id', $movement->id)->count())->toBe(2)
        ->and(MovementAllocation::query()->where('movement_id', $other_movement->id)->count())->toBe(0)
        ->and(RecordOrigin::query()->sole()->referable_id)->toBe((int) $movement->id);
});

it('rejects non-members, unbalanced totals, posted expenses, and non-expense movements atomically', function (string $scenario): void {
    [$pool, $movement, $alice, $bob, $outsider] = externalExpenseAllocationFixture();
    $shares = [
        (int) $alice->id => ['owed' => '45.0000', 'paid' => '90.0000'],
        (int) $bob->id => ['owed' => '45.0000', 'paid' => '0.0000'],
    ];

    if ($scenario === 'non-member') {
        $pool->members()->detach($outsider->id);
        $shares = [
            (int) $alice->id => ['owed' => '45.0000', 'paid' => '90.0000'],
            (int) $outsider->id => ['owed' => '45.0000', 'paid' => '0.0000'],
        ];
    } elseif ($scenario === 'unbalanced') {
        $shares[(int) $bob->id]['owed'] = '40.0000';
    } elseif ($scenario === 'posted') {
        app(MovementPostingService::class)->post($movement);
    } else {
        $movement->type = MovementType::Contribution;
        $movement->save();
    }

    expect(fn () => app(ExternalExpenseAllocationService::class)->ingest(externalExpenseAllocationInput([
        'movementId' => (int) $movement->id,
        'partnerPoolId' => (int) $pool->id,
        'shares' => $shares,
    ])))->toThrow($scenario === 'posted' ? PostedImportConflict::class : ValidationException::class)
        ->and(MovementAllocation::query()->count())->toBe(0)
        ->and(RecordOrigin::query()->count())->toBe(0)
        ->and(Movement::query()->count())->toBe(1);
})->with(['non-member', 'unbalanced', 'posted', 'contribution']);
