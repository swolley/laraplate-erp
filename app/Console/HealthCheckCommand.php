<?php

declare(strict_types=1);

namespace Modules\ERP\Console;

use JsonException;
use Modules\Core\Overrides\Command;
use Modules\ERP\Services\Diagnostics\ErpHealthCheckService;
use Override;
use Symfony\Component\Console\Command\Command as BaseCommand;

final class HealthCheckCommand extends Command
{
    #[Override]
    protected $signature = 'erp:health-check
        {--format=table : Output format: table or json}';

    #[Override]
    protected $description = 'Check ERP installation and operational prerequisites without changing data <fg=green>(Modules\ERP)</fg=green>';

    public function __construct(private readonly ErpHealthCheckService $health_check)
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

        $result = $this->health_check->run();

        if ($format === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Check', 'Status', 'Message'],
                array_map(static fn (array $check): array => [
                    $check['key'],
                    mb_strtoupper($check['status']),
                    $check['message'],
                ], $result['checks']),
            );

            $summary = $result['summary'];
            $this->line(sprintf(
                'ERP health: %d success, %d warning, %d failure.',
                $summary['success'],
                $summary['warning'],
                $summary['failure'],
            ));
        }

        return $result['summary']['failure'] === 0 ? BaseCommand::SUCCESS : BaseCommand::FAILURE;
    }
}
