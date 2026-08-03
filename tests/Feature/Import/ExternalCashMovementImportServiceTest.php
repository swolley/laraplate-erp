<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\RecordOrigin;
use Modules\Core\Import\ValueObjects\ExternalRecordIdentity;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Casts\MovementType;
use Modules\ERP\Import\Data\ExternalCashMovementInput;
use Modules\ERP\Import\Enums\ImportMutation;
use Modules\ERP\Import\Exceptions\PostedImportConflict;
use Modules\ERP\Import\Services\ExternalCashMovementImportService;
use Modules\ERP\Import\ValueObjects\CashMovementImportResult;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Movement;

uses(RefreshDatabase::class);

/** @return array{Company, Account, Account, Account, Account} */
function externalCashFixture(): array
{
    $company = Company::query()->create([
        'slug' => 'external-cash-' . uniqid(),
        'name' => 'External cash import',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $accounts = collect([
        ['1103', 'Bank', AccountKind::Asset, ['erp_role' => 'bank_cash']],
        ['4201', 'Revenue', AccountKind::Revenue, null],
        ['5603', 'Expense', AccountKind::Expense, null],
        ['2103', 'Partner current account', AccountKind::Liability, null],
    ])->map(static fn (array $attributes): Account => Account::query()->create([
        'company_id' => $company->id,
        'code' => $attributes[0],
        'name' => $attributes[1],
        'kind' => $attributes[2],
        'meta' => $attributes[3],
        'is_active' => true,
    ]))->values();

    return [$company, ...$accounts];
}

function externalCashInput(array $overrides = []): ExternalCashMovementInput
{
    return new ExternalCashMovementInput(...array_replace([
        'companyId' => 1,
        'type' => MovementType::Contribution,
        'occurredOn' => CarbonImmutable::parse('2022-12-03'),
        'amount' => '5.0000',
        'currency' => 'EUR',
        'counterpartyAccountId' => 2103,
        'description' => 'Legacy cash adjustment',
        'sourceKey' => 'legacy_symfony:nebula',
        'externalId' => 'payment:823',
        'fingerprint' => hash('sha256', 'fixture payment 823'),
        'sourceUpdatedAt' => CarbonImmutable::parse('2022-12-03T00:00:00+01:00'),
        'post' => false,
    ], $overrides));
}

it('provides typed external cash movement import primitives', function (): void {
    expect(class_exists(ExternalCashMovementInput::class))->toBeTrue()
        ->and(enum_exists(ImportMutation::class))->toBeTrue()
        ->and(class_exists(CashMovementImportResult::class))->toBeTrue()
        ->and(class_exists(PostedImportConflict::class))->toBeTrue()
        ->and(class_exists(ExternalCashMovementImportService::class))->toBeTrue();
});

it('registers the cash movement importer as a singleton', function (): void {
    expect(app(ExternalCashMovementImportService::class))
        ->toBe(app(ExternalCashMovementImportService::class));
});

it('normalizes typed cash movement input without float arithmetic', function (): void {
    $occurred_on = CarbonImmutable::parse('2022-12-03');
    $source_updated_at = CarbonImmutable::parse('2022-12-03T00:00:00+01:00');
    $fingerprint = hash('sha256', 'fixture payment 823');
    $input = new ExternalCashMovementInput(
        companyId: 1,
        type: MovementType::Contribution,
        occurredOn: $occurred_on,
        amount: '5',
        currency: 'eur',
        counterpartyAccountId: 2103,
        description: 'Legacy cash adjustment',
        sourceKey: 'legacy_symfony:nebula',
        externalId: 'payment:823',
        fingerprint: $fingerprint,
        sourceUpdatedAt: $source_updated_at,
        post: false,
    );

    expect($input->companyId)->toBe(1)
        ->and($input->type)->toBe(MovementType::Contribution)
        ->and($input->occurredOn)->toBe($occurred_on)
        ->and($input->amount)->toBe('5.0000')
        ->and($input->currency)->toBe('EUR')
        ->and($input->counterpartyAccountId)->toBe(2103)
        ->and($input->identity())->toEqual(new ExternalRecordIdentity(
            'legacy_symfony:nebula',
            'payment:823',
            $fingerprint,
            $source_updated_at,
        ));
});

it('rejects malformed cash movement input', function (array $overrides): void {
    expect(fn () => externalCashInput($overrides))->toThrow(InvalidArgumentException::class);
})->with([
    'missing company' => [['companyId' => 0]],
    'missing account' => [['counterpartyAccountId' => 0]],
    'zero amount' => [['amount' => '0']],
    'negative amount' => [['amount' => '-1']],
    'malformed amount' => [['amount' => 'not-money']],
    'malformed currency' => [['currency' => 'EURO']],
    'malformed fingerprint' => [['fingerprint' => 'not-a-sha256']],
]);

it('rejects float money at the typed boundary', function (): void {
    expect(fn () => externalCashInput(['amount' => 5.0]))->toThrow(TypeError::class);
});

it('creates an unposted movement and records its external origin', function (): void {
    [$company, , , , $liability] = externalCashFixture();
    $input = externalCashInput([
        'companyId' => (int) $company->id,
        'counterpartyAccountId' => (int) $liability->id,
    ]);

    $result = app(ExternalCashMovementImportService::class)->ingest($input);
    $movement = Movement::query()->sole();
    $origin = RecordOrigin::query()->sole();

    expect($result->mutation)->toBe(ImportMutation::Created)
        ->and($result->movementId)->toBe((int) $movement->id)
        ->and($result->journalEntryId)->toBeNull()
        ->and($movement->type)->toBe(MovementType::Contribution)
        ->and((string) $movement->amount_doc)->toBe('5')
        ->and($movement->currency_doc)->toBe('EUR')
        ->and($movement->posted_journal_entry_id)->toBeNull()
        ->and($origin->source_key)->toBe('legacy_symfony:nebula')
        ->and($origin->external_id)->toBe('payment:823')
        ->and($origin->fingerprint)->toBe($input->identity()->fingerprint);
});

it('rolls back the import when accounting references are invalid', function (): void {
    [$company] = externalCashFixture();
    $input = externalCashInput([
        'companyId' => (int) $company->id,
        'counterpartyAccountId' => 999_999,
    ]);

    expect(fn () => app(ExternalCashMovementImportService::class)->ingest($input))
        ->toThrow(ValidationException::class)
        ->and(Movement::query()->count())->toBe(0)
        ->and(RecordOrigin::query()->count())->toBe(0);
});

it('skips an unchanged external movement without writing duplicates', function (): void {
    [$company, , , , $liability] = externalCashFixture();
    $input = externalCashInput([
        'companyId' => (int) $company->id,
        'counterpartyAccountId' => (int) $liability->id,
    ]);
    $service = app(ExternalCashMovementImportService::class);

    $created = $service->ingest($input);
    $skipped = $service->ingest($input);

    expect($created->mutation)->toBe(ImportMutation::Created)
        ->and($skipped->mutation)->toBe(ImportMutation::Skipped)
        ->and($skipped->movementId)->toBe($created->movementId)
        ->and(Movement::query()->count())->toBe(1)
        ->and(RecordOrigin::query()->count())->toBe(1);
});

it('updates the same unposted movement when source evidence changes', function (): void {
    [$company, , , , $liability] = externalCashFixture();
    $base = [
        'companyId' => (int) $company->id,
        'counterpartyAccountId' => (int) $liability->id,
    ];
    $service = app(ExternalCashMovementImportService::class);
    $created = $service->ingest(externalCashInput($base));
    $changed_input = externalCashInput([
        ...$base,
        'amount' => '6.5000',
        'description' => 'Corrected legacy cash adjustment',
        'fingerprint' => hash('sha256', 'corrected fixture payment 823'),
        'sourceUpdatedAt' => CarbonImmutable::parse('2022-12-04T00:00:00+01:00'),
    ]);

    $updated = $service->ingest($changed_input);
    $movement = Movement::query()->sole();
    $origin = RecordOrigin::query()->sole();

    expect($updated->mutation)->toBe(ImportMutation::Updated)
        ->and($updated->movementId)->toBe($created->movementId)
        ->and((string) $movement->amount_doc)->toBe('6.5')
        ->and($movement->description)->toBe('Corrected legacy cash adjustment')
        ->and($origin->fingerprint)->toBe($changed_input->identity()->fingerprint)
        ->and($origin->source_updated_at?->equalTo($changed_input->identity()->sourceUpdatedAt))->toBeTrue();
});

it('posts a newly imported movement only when explicitly requested', function (): void {
    [$company, , , , $liability] = externalCashFixture();
    $input = externalCashInput([
        'companyId' => (int) $company->id,
        'counterpartyAccountId' => (int) $liability->id,
        'post' => true,
    ]);

    $result = app(ExternalCashMovementImportService::class)->ingest($input);
    $movement = Movement::query()->sole();

    expect($result->mutation)->toBe(ImportMutation::Created)
        ->and($result->journalEntryId)->not->toBeNull()
        ->and($movement->fresh()->posted_journal_entry_id)->toBe($result->journalEntryId);
});

it('rejects changed source data after the movement has been posted', function (): void {
    [$company, , , , $liability] = externalCashFixture();
    $base = [
        'companyId' => (int) $company->id,
        'counterpartyAccountId' => (int) $liability->id,
        'post' => true,
    ];
    $service = app(ExternalCashMovementImportService::class);
    $created = $service->ingest(externalCashInput($base));
    $original_origin = RecordOrigin::query()->sole();
    $exception = null;

    try {
        $service->ingest(externalCashInput([
            ...$base,
            'amount' => '9.0000',
            'fingerprint' => hash('sha256', 'changed posted payment'),
        ]));
    } catch (PostedImportConflict $caught) {
        $exception = $caught;
    }

    $movement = Movement::query()->sole();
    $origin = RecordOrigin::query()->sole();

    expect($exception)->toBeInstanceOf(PostedImportConflict::class)
        ->and($exception?->sourceKey)->toBe('legacy_symfony:nebula')
        ->and($exception?->externalId)->toBe('payment:823')
        ->and($exception?->movementId)->toBe($created->movementId)
        ->and((string) $movement->amount_doc)->toBe('5')
        ->and($movement->posted_journal_entry_id)->toBe($created->journalEntryId)
        ->and($origin->fingerprint)->toBe($original_origin->fingerprint);
});

it('writes the movement and its origin only to the configured destination connection', function (): void {
    config()->set('database.connections.erp-import-secondary', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $schema = Schema::connection('erp-import-secondary');
    $schema->create((new Company)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new Account)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('code');
        $table->string('name');
        $table->string('kind');
        $table->boolean('is_active')->default(true);
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new Movement)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('type');
        $table->date('occurred_on');
        $table->decimal('amount_doc', 16, 4);
        $table->string('currency_doc', 3);
        $table->unsignedInteger('counterparty_account_id');
        $table->unsignedInteger('posted_journal_entry_id')->nullable();
        $table->text('description')->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new RecordOrigin)->getTable(), function (Blueprint $table): void {
        $table->id();
        $table->string('referable_type');
        $table->unsignedBigInteger('referable_id');
        $table->string('source_key');
        $table->string('source_label')->nullable();
        $table->string('external_id')->nullable();
        $table->char('fingerprint', 64)->nullable();
        $table->string('url', 2048)->nullable();
        $table->timestamp('source_updated_at')->nullable();
        $table->timestamps();
        $table->unique(['referable_type', 'source_key', 'external_id']);
    });

    $connection = $schema->getConnection();
    $connection->table((new Company)->getTable())->insert([
        'id' => 8_101,
        'name' => 'Secondary import company',
    ]);
    $connection->table((new Account)->getTable())->insert([
        'id' => 8_102,
        'company_id' => 8_101,
        'code' => '2103',
        'name' => 'Partner current account',
        'kind' => AccountKind::Liability->value,
    ]);
    config()->set('erp.model_connections', [Movement::class => 'erp-import-secondary']);

    $result = app(ExternalCashMovementImportService::class)->ingest(externalCashInput([
        'companyId' => 8_101,
        'counterpartyAccountId' => 8_102,
    ]));

    expect($result->mutation)->toBe(ImportMutation::Created)
        ->and($connection->table((new Movement)->getTable())->count())->toBe(1)
        ->and($connection->table((new RecordOrigin)->getTable())->count())->toBe(1)
        ->and(Movement::query()->count())->toBe(0)
        ->and(RecordOrigin::query()->count())->toBe(0)
        ->and($connection->transactionLevel())->toBe(0);
});
