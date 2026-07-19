<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Banking;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Models\BankAccount;
use Modules\ERP\Models\BankStatement;
use Throwable;

final readonly class BankStatementBatchImportService
{
    public function __construct(
        private BankStatementImportService $import_service,
        private BankStatementCsvImporter $csv_importer,
    ) {}

    /**
     * @param  list<string>  $paths
     * @return array{files: list<array{path: string, status: 'imported'|'preview'|'skipped'|'failed', lines: int, checksum: string|null, message: string}>, summary: array{imported: int, previewed: int, skipped: int, failed: int, lines: int}}
     */
    public function import(BankAccount $bank_account, array $paths, string $format = 'auto', bool $dry_run = false, ?string $archive_path = null): array
    {
        $format = mb_strtolower($format);

        if (! in_array($format, ['auto', 'csv', 'camt053', 'mt940'], true)) {
            throw ValidationException::withMessages([
                'format' => ['Supported formats are auto, csv, camt053, and mt940.'],
            ]);
        }

        $files = [];

        foreach ($paths as $path) {
            try {
                $files[] = $this->processFile($bank_account, $path, $format, $dry_run, $archive_path);
            } catch (Throwable $exception) {
                $files[] = [
                    'path' => $path,
                    'status' => 'failed',
                    'lines' => 0,
                    'checksum' => null,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'files' => $files,
            'summary' => [
                'imported' => $this->countStatus($files, 'imported'),
                'previewed' => $this->countStatus($files, 'preview'),
                'skipped' => $this->countStatus($files, 'skipped'),
                'failed' => $this->countStatus($files, 'failed'),
                'lines' => array_sum(array_column($files, 'lines')),
            ],
        ];
    }

    /**
     * @return array{path: string, status: 'imported'|'preview'|'skipped'|'failed', lines: int, checksum: string|null, message: string}
     */
    private function processFile(BankAccount $bank_account, string $path, string $format, bool $dry_run, ?string $archive_path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw ValidationException::withMessages(['path' => [sprintf('File [%s] is not readable.', $path)]]);
        }

        $checksum = hash_file('sha256', $path);

        if (! is_string($checksum)) {
            throw ValidationException::withMessages(['path' => [sprintf('Checksum could not be calculated for [%s].', $path)]]);
        }

        $duplicate = BankStatement::query()->withoutGlobalScopes()
            ->where('bank_account_id', $bank_account->getKey())
            ->where('source_checksum', $checksum)
            ->exists();

        if ($duplicate) {
            if (! $dry_run && $archive_path !== null && mb_trim($archive_path) !== '') {
                $this->archive($path, $archive_path, $checksum);
            }

            return [
                'path' => $path,
                'status' => 'skipped',
                'lines' => 0,
                'checksum' => $checksum,
                'message' => 'File checksum was already imported for this bank account.',
            ];
        }

        $lines = $this->parse($path, $format);

        if ($dry_run) {
            return [
                'path' => $path,
                'status' => 'preview',
                'lines' => count($lines),
                'checksum' => $checksum,
                'message' => 'File parsed successfully; no data was written.',
            ];
        }

        $statement = DB::transaction(function () use ($bank_account, $path, $checksum, $lines): BankStatement {
            $existing = BankStatement::query()->withoutGlobalScopes()
                ->where('bank_account_id', $bank_account->getKey())
                ->where('source_checksum', $checksum)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof BankStatement) {
                return $existing;
            }

            $statement = BankStatement::query()->withoutGlobalScopes()->create([
                'company_id' => $bank_account->company_id,
                'bank_account_id' => $bank_account->getKey(),
                'source_filename' => basename($path),
                'source_checksum' => $checksum,
            ]);

            $this->import_service->importLines($statement, $lines);

            $dates = $statement->lines()->pluck('booked_at')->filter()->sort()->values();
            $statement->period_start = $dates->first();
            $statement->period_end = $dates->last();
            $statement->save();

            return $statement;
        });

        if ($archive_path !== null && mb_trim($archive_path) !== '') {
            $this->archive($path, $archive_path, $checksum);
        }

        return [
            'path' => $path,
            'status' => 'imported',
            'lines' => $statement->lines()->count(),
            'checksum' => $checksum,
            'message' => sprintf('Imported as bank statement #%s.', $statement->getKey()),
        ];
    }

    /**
     * @return list<BankStatementLineData>
     */
    private function parse(string $path, string $format): array
    {
        if ($format === 'csv' || ($format === 'auto' && str_ends_with(mb_strtolower($path), '.csv'))) {
            return $this->csv_importer->parse($path);
        }

        return $this->import_service->parseFile($path, $format);
    }

    private function archive(string $path, string $archive_path, string $checksum): void
    {
        File::ensureDirectoryExists($archive_path);
        $destination = rtrim($archive_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($path);

        if (file_exists($destination)) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $stem = pathinfo($path, PATHINFO_FILENAME);
            $destination = rtrim($archive_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                . $stem . '-' . mb_substr($checksum, 0, 12)
                . ($extension !== '' ? '.' . $extension : '');
        }

        if (! File::move($path, $destination)) {
            throw ValidationException::withMessages(['archive_path' => [sprintf('Imported file [%s] could not be archived.', $path)]]);
        }
    }

    /**
     * @param  list<array{path: string, status: string, lines: int, checksum: string|null, message: string}>  $files
     */
    private function countStatus(array $files, string $status): int
    {
        return count(array_filter($files, static fn (array $file): bool => $file['status'] === $status));
    }
}
