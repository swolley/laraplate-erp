<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Version;
use Modules\Core\Models\VersionSet;
use Modules\ERP\Casts\BillingMode;
use Modules\ERP\Casts\EInvoiceSubmissionStatus;
use Modules\ERP\Casts\MovementType;
use Modules\ERP\Casts\QuoteStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\EInvoiceSubmission;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\Movement;
use Modules\ERP\Models\PartnerPool;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\Quotation;
use Modules\ERP\Models\QuotationItem;
use Modules\ERP\Services\Cash\MovementPostingService;
use Modules\ERP\Services\Cash\PartnerPoolSettlementService;
use Modules\ERP\Services\EInvoice\EInvoiceSubmissionService;
use Modules\ERP\Services\Quotations\QuotationRevisionService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('database.connections.erp-direct-secondary', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $schema = Schema::connection('erp-direct-secondary');
    $schema->create((new Company)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new VersionSet)->getTable(), function (Blueprint $table): void {
        $table->id();
        $table->uuid('uuid')->unique();
        $table->string('root_type')->nullable();
        $table->string('root_id')->nullable();
        $table->string('root_connection_ref')->nullable();
        $table->string('root_table_ref')->nullable();
        $table->unsignedBigInteger(config('versionable.user_foreign_key', 'user_id'))->nullable();
        $table->string('kind');
        $table->string('reason')->nullable();
        $table->unsignedBigInteger('reverted_from_set_id')->nullable();
        $table->timestamps();
    });
    $schema->create((new Version)->getTable(), function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('version_set_id');
        $table->unsignedInteger('sequence');
        $table->string('change_type');
        $table->string('relation_path')->nullable();
        $table->json('subject_key')->nullable();
        $table->string('connection_ref')->nullable();
        $table->string('table_ref')->nullable();
        $table->unsignedBigInteger(config('versionable.user_foreign_key', 'user_id'))->nullable();
        $table->unsignedBigInteger('versionable_id');
        $table->string('versionable_type');
        $table->json('original_contents')->nullable();
        $table->json('contents')->nullable();
        $table->string('version_strategy');
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->default(false);
        $table->unique(['version_set_id', 'sequence']);
    });
});

it('creates quotation revisions only on the source affinity connection', function (): void {
    $schema = Schema::connection('erp-direct-secondary');
    $schema->create((new Party)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('name');
        $table->boolean('is_customer')->default(false);
        $table->boolean('is_supplier')->default(false);
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new Quotation)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('party_id');
        $table->unsignedInteger('opportunity_id')->nullable();
        $table->string('currency', 3);
        $table->text('notes')->nullable();
        $table->string('status');
        $table->unsignedTinyInteger('version')->default(0);
        $table->unsignedInteger('revises_quotation_id')->nullable();
        $table->date('valid_from')->nullable();
        $table->date('valid_to')->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new QuotationItem)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('quotation_id');
        $table->unsignedInteger('price_list_item_id')->nullable();
        $table->string('name');
        $table->string('billing_mode');
        $table->decimal('quantity', 16, 4);
        $table->decimal('unit_price', 16, 4)->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });

    $connection = $schema->getConnection();
    $connection->table((new Company)->getTable())->insert([
        'id' => 9101,
        'name' => 'Secondary quotation company',
    ]);
    $connection->table((new Party)->getTable())->insert([
        'id' => 9101,
        'company_id' => 9101,
        'name' => 'Secondary quotation customer',
        'is_customer' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $connection->table((new Quotation)->getTable())->insert([
        'id' => 9101,
        'company_id' => 9101,
        'party_id' => 9101,
        'currency' => 'EUR',
        'notes' => 'Secondary quote',
        'status' => QuoteStatus::Sent->value,
        'version' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $connection->table((new QuotationItem)->getTable())->insert([
        'id' => 9101,
        'quotation_id' => 9101,
        'name' => 'Secondary consulting',
        'billing_mode' => BillingMode::Unit->value,
        'quantity' => '2.0000',
        'unit_price' => '75.0000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $source = (new Quotation)->setConnection('erp-direct-secondary');
    $source->id = 9101;
    $source->exists = true;

    $revision = app(QuotationRevisionService::class)->createRevision($source);

    expect($revision->getConnectionName())->toBe('erp-direct-secondary')
        ->and($connection->table((new Quotation)->getTable())->where('revises_quotation_id', 9101)->count())->toBe(1)
        ->and(Quotation::query()->where('revises_quotation_id', 9101)->count())->toBe(0)
        ->and($connection->transactionLevel())->toBe(0);
});

it('applies e-invoice callbacks only on the configured trusted connection', function (): void {
    $schema = Schema::connection('erp-direct-secondary');
    $schema->create((new Invoice)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new EInvoiceSubmission)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('invoice_id');
        $table->string('provider_code');
        $table->string('external_id')->nullable();
        $table->string('status');
        $table->text('last_payload_path')->nullable();
        $table->timestamp('submitted_at')->nullable();
        $table->json('response_payload')->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });
    $connection = $schema->getConnection();
    $connection->table((new Invoice)->getTable())->insert([
        'id' => 9201,
        'company_id' => 9201,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $connection->table((new EInvoiceSubmission)->getTable())->insert([
        'id' => 9201,
        'company_id' => 9201,
        'invoice_id' => 9201,
        'provider_code' => 'aruba',
        'external_id' => 'secondary-invoice.xml.p7m',
        'status' => EInvoiceSubmissionStatus::Submitted->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    config()->set('erp.model_connections', [
        EInvoiceSubmission::class => 'erp-direct-secondary',
    ]);

    $updated = app(EInvoiceSubmissionService::class)->applyProviderCallback('aruba', [
        'invoiceFileName' => 'secondary-invoice.xml.p7m',
        'notifyType' => 'RC',
        'result' => 'EC01',
    ]);

    expect($updated->getConnectionName())->toBe('erp-direct-secondary')
        ->and($connection->table((new EInvoiceSubmission)->getTable())->where('id', 9201)->value('status'))->toBe(EInvoiceSubmissionStatus::Accepted->value)
        ->and(EInvoiceSubmission::query()->whereKey(9201)->count())->toBe(0)
        ->and($connection->transactionLevel())->toBe(0);
});

it('resolves already posted movements only on their affinity connection', function (): void {
    $schema = Schema::connection('erp-direct-secondary');
    $schema->create((new JournalEntry)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->unsignedInteger('fiscal_period_id')->nullable();
        $table->timestamp('posted_at')->nullable();
        $table->unsignedInteger('posted_by')->nullable();
        $table->string('reference_type')->nullable();
        $table->unsignedInteger('reference_id')->nullable();
        $table->text('description')->nullable();
        $table->unsignedInteger('reverses_journal_entry_id')->nullable();
        $table->text('reversal_reason')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->default(false);
    });
    $schema->create((new Movement)->getTable(), function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('company_id');
        $table->string('type');
        $table->date('occurred_on');
        $table->decimal('amount_doc', 16, 4);
        $table->string('currency_doc', 3);
        $table->decimal('amount_local', 16, 4)->nullable();
        $table->string('currency_local', 3)->nullable();
        $table->decimal('fx_rate', 18, 8)->nullable();
        $table->unsignedInteger('counterparty_account_id');
        $table->unsignedInteger('posted_journal_entry_id')->nullable();
        $table->text('description')->nullable();
        $table->timestamps();
        $table->boolean('is_deleted')->default(false);
    });
    $connection = $schema->getConnection();
    $connection->table((new JournalEntry)->getTable())->insert([
        'id' => 9251,
        'company_id' => 9251,
        'posted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $connection->table((new Movement)->getTable())->insert([
        'id' => 9251,
        'company_id' => 9251,
        'type' => MovementType::Income->value,
        'occurred_on' => '2026-07-28',
        'amount_doc' => '10.0000',
        'currency_doc' => 'EUR',
        'counterparty_account_id' => 9251,
        'posted_journal_entry_id' => 9251,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $movement = (new Movement)->setConnection('erp-direct-secondary');
    $movement->id = 9251;
    $movement->exists = true;

    $entry = app(MovementPostingService::class)->post($movement);

    expect($entry->getConnectionName())->toBe('erp-direct-secondary')
        ->and($entry->getKey())->toBe(9251)
        ->and(Movement::query()->whereKey(9251)->count())->toBe(0)
        ->and(JournalEntry::query()->whereKey(9251)->count())->toBe(0)
        ->and($connection->transactionLevel())->toBe(0);
});

it('rejects mismatched aggregate participants before partner pool writes', function (): void {
    $movement = (new Movement)->setConnection('erp-direct-secondary');
    $movement->id = 9301;
    $movement->exists = true;
    $pool = (new PartnerPool)->setConnection(config('database.default'));
    $pool->id = 9301;
    $pool->exists = true;

    expect(fn () => app(PartnerPoolSettlementService::class)->allocate($movement, $pool, []))
        ->toThrow(LogicException::class, 'same database connection')
        ->and($movement->getConnection()->transactionLevel())->toBe(0);
});
