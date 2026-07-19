<?php

declare(strict_types=1);

namespace Modules\ERP\Console;

use Illuminate\Support\Facades\File;
use JsonException;
use Modules\Core\Overrides\Command;
use Modules\ERP\Models\BankAccount;
use Modules\ERP\Services\Banking\BankStatementBatchImportService;
use Override;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Throwable;

final class BankStatementsImportCommand extends Command
{
    #[Override]
    protected $signature = 'erp:bank-statements:import
        {--bank-account= : Required bank account id}
        {--path= : Required file or directory path}
        {--format=auto : auto|csv|camt053|mt940}
        {--archive-path= : Move successfully imported files to this directory}
        {--dry-run : Parse and validate without creating statements or moving files}
        {--output=table : Output format: table or json}';

    #[Override]
    protected $description = 'Import bank statement files in controlled idempotent batches <fg=green>(Modules\ERP)</fg=green>';

    public function __construct(private readonly BankStatementBatchImportService $batch_import)
    {
        parent::__construct();
    }

    /**
     * @throws JsonException
     */
    public function handle(): int
    {
        $output = mb_strtolower((string) $this->option('output'));

        if (! in_array($output, ['table', 'json'], true)) {
            $this->error('The --output option must be table or json.');

            return BaseCommand::INVALID;
        }

        $bank_account_id = $this->option('bank-account');

        if (! is_numeric($bank_account_id) || (int) $bank_account_id <= 0) {
            $this->error('The --bank-account option is required and must be a positive id.');

            return BaseCommand::INVALID;
        }

        $path = (string) $this->option('path');

        if ($path === '') {
            $this->error('The --path option is required.');

            return BaseCommand::INVALID;
        }

        try {
            $bank_account = BankAccount::query()->withoutGlobalScopes()->find((int) $bank_account_id);

            if (! $bank_account instanceof BankAccount) {
                $this->error('The requested bank account does not exist.');

                return BaseCommand::FAILURE;
            }

            $paths = $this->resolvePaths($path);
            $result = $this->batch_import->import(
                $bank_account,
                $paths,
                (string) $this->option('format'),
                (bool) $this->option('dry-run'),
                $this->archivePath(),
            );
        } catch (Throwable $exception) {
            $this->error('Bank statement batch import failed: ' . $exception->getMessage());

            return BaseCommand::FAILURE;
        }

        if ($output === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['File', 'Status', 'Lines', 'Message'],
                array_map(static fn (array $file): array => [
                    $file['path'],
                    mb_strtoupper($file['status']),
                    $file['lines'],
                    $file['message'],
                ], $result['files']),
            );

            $summary = $result['summary'];
            $this->line(sprintf(
                'Bank import: %d imported, %d previewed, %d skipped, %d failed, %d lines.',
                $summary['imported'],
                $summary['previewed'],
                $summary['skipped'],
                $summary['failed'],
                $summary['lines'],
            ));
        }

        return $result['summary']['failed'] === 0 ? BaseCommand::SUCCESS : BaseCommand::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function resolvePaths(string $path): array
    {
        if (is_file($path)) {
            return [$path];
        }

        if (! is_dir($path)) {
            throw new \InvalidArgumentException(sprintf('Path [%s] is neither a file nor a directory.', $path));
        }

        return collect(File::files($path))
            ->filter(static fn (\SplFileInfo $file): bool => in_array(mb_strtolower($file->getExtension()), ['csv', 'xml', 'sta', 'mt940'], true))
            ->sortBy(static fn (\SplFileInfo $file): string => $file->getFilename())
            ->map(static fn (\SplFileInfo $file): string => $file->getPathname())
            ->values()
            ->all();
    }

    private function archivePath(): ?string
    {
        if ((bool) $this->option('dry-run')) {
            return null;
        }

        $archive_path = $this->option('archive-path');

        return is_string($archive_path) && mb_trim($archive_path) !== '' ? $archive_path : null;
    }
}
