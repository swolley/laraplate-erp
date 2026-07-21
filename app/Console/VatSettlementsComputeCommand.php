<?php

declare(strict_types=1);

namespace Modules\ERP\Console;

use JsonException;
use Modules\Core\Overrides\Command;
use Modules\ERP\Models\Company;
use Modules\ERP\Services\Accounting\VatSettlementBatchService;
use Override;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Throwable;

final class VatSettlementsComputeCommand extends Command
{
    #[Override]
    protected $signature = 'erp:vat-settlements:compute
        {--company= : Company id; defaults to the default ERP company}
        {--year= : Fiscal year; defaults to the current year}
        {--period= : Optional period in YYYY-N format}
        {--dry-run : Compute amounts without creating or updating settlements}
        {--format=table : Output format: table or json}';

    #[Override]
    protected $description = 'Compute draft VAT settlements for open fiscal periods <fg=green>(Modules\ERP)</fg=green>';

    public function __construct(private readonly VatSettlementBatchService $batch_service)
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

            if (! $company instanceof Company) {
                $this->error('The requested company does not exist and no default ERP company is configured.');

                return BaseCommand::FAILURE;
            }

            $year = (int) ($this->option('year') ?: now()->year);

            if ($year < 1900 || $year > 2100) {
                $this->error('The --year option must be between 1900 and 2100.');

                return BaseCommand::INVALID;
            }

            $result = $this->batch_service->compute(
                (int) $company->getKey(),
                $year,
                $this->periodOption(),
                (bool) $this->option('dry-run'),
            );
        } catch (Throwable $exception) {
            $this->error('VAT settlement batch computation failed: ' . $exception->getMessage());

            return BaseCommand::FAILURE;
        }

        if ($format === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Period', 'Status', 'Sales VAT', 'Purchase VAT', 'Credit', 'Settlement', 'Message'],
                array_map(static fn (array $period): array => [
                    $period['period'],
                    mb_strtoupper($period['status']),
                    $period['vat_sales'] ?? '-',
                    $period['vat_purchases'] ?? '-',
                    $period['previous_credit'] ?? '-',
                    $period['settlement_amount'] ?? '-',
                    $period['message'],
                ], $result['periods']),
            );

            $summary = $result['summary'];
            $this->line(sprintf(
                'VAT settlements: %d computed, %d previewed, %d skipped, %d failed.',
                $summary['computed'],
                $summary['previewed'],
                $summary['skipped'],
                $summary['failed'],
            ));
        }

        return $result['summary']['failed'] === 0 ? BaseCommand::SUCCESS : BaseCommand::FAILURE;
    }

    private function resolveCompany(): ?Company
    {
        $company_id = $this->option('company');

        if ($company_id !== null && $company_id !== '') {
            return is_numeric($company_id) && (int) $company_id > 0
                ? Company::query()->withoutGlobalScopes()->find((int) $company_id)
                : null;
        }

        return Company::getDefault();
    }

    private function periodOption(): ?string
    {
        $period = $this->option('period');

        return is_string($period) && mb_trim($period) !== '' ? $period : null;
    }
}
