<?php

declare(strict_types=1);

namespace Modules\ERP\Console;

use JsonException;
use Modules\Core\Overrides\Command;
use Modules\ERP\Models\Company;
use Modules\ERP\Services\Accounting\DocumentSequenceAuditService;
use Override;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Throwable;

final class DocumentSequencesAuditCommand extends Command
{
    #[Override]
    protected $signature = 'erp:sequences:audit
        {--company= : Company id; defaults to the default ERP company}
        {--year= : Fiscal year; defaults to the current year}
        {--format=table : Output format: table or json}';

    #[Override]
    protected $description = 'Audit ERP document sequence counters against persisted references without changing data <fg=green>(Modules\ERP)</fg=green>';

    public function __construct(private readonly DocumentSequenceAuditService $audit_service)
    {
        parent::__construct();
    }

    /**
     * @throws JsonException
     */
    public function handle(): int
    {
        $format = mb_strtolower((string) $this->option('format'));

        if (! in_array($format, ['table', 'json'], true)) {
            $this->error('The --format option must be table or json.');

            return BaseCommand::INVALID;
        }

        try {
            $company = $this->resolveCompany();
        } catch (Throwable $exception) {
            $this->error('Unable to resolve the ERP company: ' . $exception->getMessage());

            return BaseCommand::FAILURE;
        }

        if (! $company instanceof Company) {
            $this->error('The requested company does not exist and no default ERP company is configured.');

            return BaseCommand::FAILURE;
        }

        $year = (int) ($this->option('year') ?: now()->year);

        if ($year < 1900 || $year > 2100) {
            $this->error('The --year option must be between 1900 and 2100.');

            return BaseCommand::INVALID;
        }

        try {
            $result = $this->audit_service->audit((int) $company->getKey(), $year);
        } catch (Throwable $exception) {
            $this->error('Unable to audit ERP document sequences: ' . $exception->getMessage());

            return BaseCommand::FAILURE;
        }

        if ($format === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Stream', 'Status', 'Code', 'Message'],
                array_map(static fn (array $check): array => [
                    $check['document_type'],
                    mb_strtoupper($check['status']),
                    $check['code'],
                    $check['message'],
                ], $result['checks']),
            );

            $summary = $result['summary'];
            $this->line(sprintf(
                'Sequence audit: %d success, %d warning, %d failure.',
                $summary['success'],
                $summary['warning'],
                $summary['failure'],
            ));
        }

        return $result['summary']['failure'] === 0 ? BaseCommand::SUCCESS : BaseCommand::FAILURE;
    }

    private function resolveCompany(): ?Company
    {
        $company_id = $this->option('company');

        if ($company_id !== null && $company_id !== '') {
            if (! is_numeric($company_id) || (int) $company_id <= 0) {
                return null;
            }

            return Company::query()->withoutGlobalScopes()->find((int) $company_id);
        }

        return Company::getDefault();
    }
}
