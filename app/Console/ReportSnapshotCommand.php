<?php

declare(strict_types=1);

namespace Modules\ERP\Console;

use Carbon\CarbonImmutable;
use Modules\Core\Overrides\Command;
use Modules\ERP\Services\Reporting\BalanceSheetService;
use Modules\ERP\Services\Reporting\FinancialReportCsvExporter;
use Modules\ERP\Services\Reporting\IncomeStatementService;
use Modules\ERP\Services\Reporting\ReportSnapshotService;
use Modules\ERP\Services\Reporting\TrialBalanceService;
use Override;
use Symfony\Component\Console\Command\Command as BaseCommand;

final class ReportSnapshotCommand extends Command
{
    #[Override]
    protected $signature = 'erp:reports:snapshot
        {report : trial_balance|income_statement|balance_sheet}
        {--company= : Company id}
        {--from= : Period start date for income statement}
        {--to= : Period end / as-of date}
        {--dry-run : Generate report but do not archive}';

    #[Override]
    protected $description = 'Archive immutable ERP report snapshots with CSV and PDF content <fg=green>(Modules\ERP)</fg=green>';

    public function __construct(
        private readonly TrialBalanceService $trial_balance_service,
        private readonly IncomeStatementService $income_statement_service,
        private readonly BalanceSheetService $balance_sheet_service,
        private readonly FinancialReportCsvExporter $csv_exporter,
        private readonly ReportSnapshotService $snapshot_service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $company_id = (int) $this->option('company');

        if ($company_id <= 0) {
            $this->error('The --company option is required.');

            return BaseCommand::FAILURE;
        }

        [$title, $payload, $csv, $parameters] = $this->generate($company_id, (string) $this->argument('report'));

        if ((bool) $this->option('dry-run')) {
            $this->info(sprintf('Generated %s snapshot preview (%d CSV bytes).', $title, strlen($csv)));

            return BaseCommand::SUCCESS;
        }

        $snapshot = $this->snapshot_service->archive(
            company_id: $company_id,
            report_key: (string) $this->argument('report'),
            title: $title,
            parameters: $parameters,
            payload: $payload,
            csv_content: $csv,
        );

        $this->info(sprintf('Archived report snapshot #%s [%s].', $snapshot->id, $snapshot->content_hash));

        return BaseCommand::SUCCESS;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: string, 3: array<string, mixed>}
     */
    private function generate(int $company_id, string $report): array
    {
        $to = CarbonImmutable::parse((string) ($this->option('to') ?: now()->toDateString()))->endOfDay();
        $from = CarbonImmutable::parse((string) ($this->option('from') ?: $to->startOfYear()->toDateString()))->startOfDay();

        return match ($report) {
            'trial_balance' => (function () use ($company_id, $to): array {
                $rows = $this->trial_balance_service->generate($company_id, $to);

                return ['Trial balance', ['rows' => $rows], $this->csv_exporter->trialBalance($rows), ['as_of' => $to->toDateString()]];
            })(),
            'income_statement' => (function () use ($company_id, $from, $to): array {
                $report = $this->income_statement_service->generate($company_id, $from, $to);

                return ['Income statement', $report, $this->csv_exporter->incomeStatement($report), ['from' => $from->toDateString(), 'to' => $to->toDateString()]];
            })(),
            'balance_sheet' => (function () use ($company_id, $to): array {
                $report = $this->balance_sheet_service->generate($company_id, $to);

                return ['Balance sheet', $report, $this->csv_exporter->balanceSheet($report), ['as_of' => $to->toDateString()]];
            })(),
            default => throw new \InvalidArgumentException('Unsupported report snapshot key.'),
        };
    }
}
