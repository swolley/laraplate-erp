<?php

declare(strict_types=1);

namespace Modules\ERP\Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Services\Accounting\DocumentNumberAllocator;
use Throwable;

final class DocumentNumberConcurrencyHarness
{
    /**
     * @return array{errors: list<string>, failed_children: list<int>, numbers: list<string>, last_number: int, sequence_count: int}
     */
    public static function run(int $process_count): array
    {
        $original_default = config('database.default');
        $original_sqlite_database = config('database.connections.sqlite.database');
        $database_path = tempnam(sys_get_temp_dir(), 'erp-seq-stress-')
            ?: sys_get_temp_dir().'/erp-seq-stress-'.uniqid().'.sqlite';
        $start_path = $database_path.'.start';
        $result_dir = $database_path.'.results';
        $children = [];

        mkdir($result_dir);

        try {
            self::useDatabase($database_path);
            $company_id = self::createSchema();

            DB::table(ERPTables::DocumentSequences->value)->insert([
                'company_id' => $company_id,
                'document_type' => DocumentType::SalesInvoice->value,
                'fiscal_year' => 2026,
                'last_number' => 0,
                'gap_allowed' => false,
                'prefix' => '',
                'padding' => 5,
                'format_pattern' => null,
                'suffix' => '',
                'created_at' => now(),
                'updated_at' => now(),
                'is_deleted' => false,
            ]);

            DB::disconnect('sqlite');

            for ($worker = 0; $worker < $process_count; $worker++) {
                $pid = pcntl_fork();

                if ($pid === -1) {
                    throw new \RuntimeException('Unable to fork document-number stress worker.');
                }

                if ($pid === 0) {
                    self::runWorker($database_path, $start_path, $result_dir, $company_id);
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

            self::useDatabase($database_path);

            $payloads = collect(glob($result_dir.'/*.json') ?: [])
                ->map(static fn (string $path): array => json_decode(
                    (string) file_get_contents($path),
                    true,
                    flags: JSON_THROW_ON_ERROR,
                ));
            $sequence = DocumentSequence::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company_id)
                ->where('document_type', DocumentType::SalesInvoice)
                ->where('fiscal_year', 2026)
                ->first();

            return [
                'errors' => $payloads
                    ->reject(static fn (array $payload): bool => (bool) ($payload['ok'] ?? false))
                    ->pluck('error')->values()->all(),
                'failed_children' => $failed_children,
                'numbers' => $payloads
                    ->filter(static fn (array $payload): bool => (bool) ($payload['ok'] ?? false))
                    ->pluck('number')->sort()->values()->all(),
                'last_number' => (int) $sequence?->last_number,
                'sequence_count' => DocumentSequence::query()->withoutGlobalScopes()->count(),
            ];
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

            foreach (glob($result_dir.'/*.json') ?: [] as $path) {
                @unlink($path);
            }

            @rmdir($result_dir);
            @unlink($start_path);
            @unlink($database_path);
        }
    }

    private static function runWorker(
        string $database_path,
        string $start_path,
        string $result_dir,
        int $company_id,
    ): never {
        try {
            while (! file_exists($start_path)) {
                usleep(1000);
            }

            usleep(random_int(5_000, 100_000));
            self::useDatabase($database_path);
            $number = app(DocumentNumberAllocator::class)->next(
                self::company($company_id),
                DocumentType::SalesInvoice,
                2026,
            );
            self::writeResult($result_dir, ['ok' => true, 'number' => $number]);

            exit(0);
        } catch (Throwable $throwable) {
            self::writeResult($result_dir, [
                'ok' => false,
                'error' => $throwable::class.': '.$throwable->getMessage(),
            ]);

            exit(1);
        }
    }

    /** @param array<string, bool|string> $payload */
    private static function writeResult(string $result_dir, array $payload): void
    {
        file_put_contents(
            $result_dir.'/'.getmypid().'.json',
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    private static function company(int $company_id): Company
    {
        $company = new Company;
        $company->forceFill(['id' => $company_id]);
        $company->exists = true;

        return $company;
    }

    private static function useDatabase(string $database_path): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $database_path,
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::statement('PRAGMA journal_mode = WAL');
        DB::statement('PRAGMA busy_timeout = 30000');
    }

    private static function createSchema(): int
    {
        Schema::connection('sqlite')->create('vend_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection('sqlite')->create('core_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group_name');
            $table->string('name');
            $table->text('payload')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
            $table->timestamps();
            $table->softDeletes();
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
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('is_deleted')->default(false);
            $table->unique(['company_id', 'document_type', 'fiscal_year']);
        });

        return (int) DB::table(ERPTables::Companies->value)->insertGetId([
            'slug' => 'concurrency-stress',
            'name' => 'Concurrency Stress',
            'fiscal_country' => 'IT',
            'default_currency' => 'EUR',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
