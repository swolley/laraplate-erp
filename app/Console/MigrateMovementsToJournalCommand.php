<?php

declare(strict_types=1);

namespace Modules\ERP\Console;

use Modules\Core\Overrides\Command;
use Modules\ERP\Models\Movement;
use Modules\ERP\Services\Cash\MovementPostingService;
use Override;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Throwable;

final class MigrateMovementsToJournalCommand extends Command
{
    #[Override]
    protected $signature = 'erp:migrate-movements-to-journal
        {--company= : Restrict to one ERP company id}
        {--dry-run : List pending movements without posting journals}';

    #[Override]
    protected $description = 'Post unlinked ERP cash movements to journal entries idempotently <fg=green>(Modules\ERP)</fg=green>';

    public function __construct(private readonly MovementPostingService $posting_service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Movement::query()->withoutGlobalScopes()->whereNull('posted_journal_entry_id')->orderBy('id');
        $company_id = $this->option('company');

        if ($company_id !== null && (! is_numeric($company_id) || (int) $company_id <= 0)) {
            $this->error('The --company option must be a positive integer.');

            return BaseCommand::INVALID;
        }

        if ($company_id !== null) {
            $query->where('company_id', (int) $company_id);
        }

        $pending = $query->get();

        if ((bool) $this->option('dry-run')) {
            $this->info(sprintf('%d movement(s) would be posted.', $pending->count()));

            return BaseCommand::SUCCESS;
        }

        $posted = 0;
        $failed = 0;

        foreach ($pending as $movement) {
            try {
                $this->posting_service->post($movement);
                $posted++;
            } catch (Throwable $exception) {
                $failed++;
                $this->error(sprintf('Movement #%s failed: %s', $movement->id, $exception->getMessage()));
            }
        }

        $this->info(sprintf('%d movement(s) posted; %d failed.', $posted, $failed));

        return $failed === 0 ? BaseCommand::SUCCESS : BaseCommand::FAILURE;
    }
}
