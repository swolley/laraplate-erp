<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Services\Accounting\DocumentNumberAllocator;

function documentNumberConcurrencyCompany(int $company_id): Company
{
    $company = new Company;
    $company->forceFill(['id' => $company_id]);
    $company->exists = true;

    return $company;
}

function documentNumberConcurrencyUseDatabase(string $database_path): void
{
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $database_path,
        'database.connections.sqlite.foreign_key_constraints' => true,
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');
    DB::statement('PRAGMA busy_timeout = 10000');
}

function documentNumberConcurrencyCreateSchema(): int
{
    Schema::connection('sqlite')->create('vend_permissions', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('guard_name')->default('web');
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();

        $table->unique(['name', 'guard_name']);
    });

    Schema::connection('sqlite')->create('core_settings', function (Blueprint $table): void {
        $table->id();
        $table->string('group_name');
        $table->string('name');
        $table->text('payload')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
        $table->timestamp('deleted_at')->nullable();
        $table->boolean('is_deleted')->default(false);
    });

    Schema::connection('sqlite')->create(ERPTables::Companies->value, function (Blueprint $table): void {
        $table->id();
        $table->string('slug')->unique();
        $table->string('name');
        $table->string('legal_name')->nullable();
        $table->string('tax_id')->nullable();
        $table->string('fiscal_country', 2)->default('IT');
        $table->char('default_currency', 3)->default('EUR');
        $table->json('settings')->nullable();
        $table->boolean('is_default')->default(false);
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
        $table->timestamp('deleted_at')->nullable();
        $table->boolean('is_deleted')->default(false);
    });

    Schema::connection('sqlite')->create(ERPTables::DocumentSequences->value, function (Blueprint $table): void {
        $table->id();
        $table->foreignId('company_id')->constrained(ERPTables::Companies->value)->restrictOnDelete();
        $table->string('document_type');
        $table->unsignedSmallInteger('fiscal_year')->default(0);
        $table->unsignedBigInteger('last_number')->default(0);
        $table->boolean('gap_allowed')->default(false);
        $table->string('prefix', 32)->default('');
        $table->unsignedTinyInteger('padding')->default(5);
        $table->string('format_pattern')->nullable();
        $table->string('suffix', 32)->default('');
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
        $table->timestamp('deleted_at')->nullable();
        $table->boolean('is_deleted')->default(false);

        $table->unique(['company_id', 'document_type', 'fiscal_year']);
    });

    return (int) DB::table(ERPTables::Companies->value)->insertGetId([
        'slug' => 'concurrency',
        'name' => 'Concurrency',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('allocates one contiguous fiscal sequence under concurrent requests', function (): void {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl is required for document number concurrency coverage.');
    }

    $original_default = config('database.default');
    $original_sqlite_database = config('database.connections.sqlite.database');
    $database_path = tempnam(sys_get_temp_dir(), 'erp-seq-') ?: sys_get_temp_dir() . '/erp-seq-' . uniqid() . '.sqlite';
    $start_path = $database_path . '.start';
    $result_dir = $database_path . '.results';
    $process_count = 12;
    $children = [];

    mkdir($result_dir);

    try {
        documentNumberConcurrencyUseDatabase($database_path);
        $company_id = documentNumberConcurrencyCreateSchema();

        DB::disconnect('sqlite');

        for ($i = 0; $i < $process_count; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('Unable to fork child process for document number concurrency test.');
            }

            if ($pid === 0) {
                try {
                    while (! file_exists($start_path)) {
                        usleep(1000);
                    }

                    documentNumberConcurrencyUseDatabase($database_path);

                    $number = app(DocumentNumberAllocator::class)->next(
                        documentNumberConcurrencyCompany($company_id),
                        DocumentType::SalesInvoice,
                        2026,
                    );

                    file_put_contents($result_dir . '/' . getmypid() . '.json', json_encode([
                        'ok' => true,
                        'number' => $number,
                    ], JSON_THROW_ON_ERROR));

                    exit(0);
                } catch (Throwable $throwable) {
                    file_put_contents($result_dir . '/' . getmypid() . '.json', json_encode([
                        'ok' => false,
                        'error' => $throwable::class . ': ' . $throwable->getMessage(),
                    ], JSON_THROW_ON_ERROR));

                    exit(1);
                }
            }

            $children[] = $pid;
        }

        touch($start_path);

        $failed_children = [];
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);

            if (pcntl_wexitstatus($status) !== 0) {
                $failed_children[] = $pid;
            }
        }

        documentNumberConcurrencyUseDatabase($database_path);

        $payloads = collect(glob($result_dir . '/*.json') ?: [])
            ->map(static fn (string $path): array => json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR));
        $numbers = $payloads
            ->filter(static fn (array $payload): bool => (bool) ($payload['ok'] ?? false))
            ->pluck('number')
            ->sort()
            ->values()
            ->all();
        $errors = $payloads
            ->reject(static fn (array $payload): bool => (bool) ($payload['ok'] ?? false))
            ->pluck('error')
            ->values()
            ->all();
        $sequence = DocumentSequence::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company_id)
            ->where('document_type', DocumentType::SalesInvoice)
            ->where('fiscal_year', 2026)
            ->first();

        expect($errors)->toBe([])
            ->and($failed_children)->toBe([])
            ->and($numbers)->toHaveCount($process_count)
            ->and($numbers)->toBe([
                '2026-00001',
                '2026-00002',
                '2026-00003',
                '2026-00004',
                '2026-00005',
                '2026-00006',
                '2026-00007',
                '2026-00008',
                '2026-00009',
                '2026-00010',
                '2026-00011',
                '2026-00012',
            ])
            ->and($sequence)->not->toBeNull()
            ->and((int) $sequence?->last_number)->toBe($process_count)
            ->and(DocumentSequence::query()->withoutGlobalScopes()->count())->toBe(1);
    } finally {
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status, WNOHANG);
        }

        config([
            'database.default' => $original_default,
            'database.connections.sqlite.database' => $original_sqlite_database,
        ]);
        DB::purge('sqlite');
        DB::reconnect();

        foreach (glob($result_dir . '/*.json') ?: [] as $path) {
            @unlink($path);
        }

        @rmdir($result_dir);
        @unlink($start_path);
        @unlink($database_path);
    }
});
