<?php

declare(strict_types=1);

namespace Modules\ERP\Console;

use Illuminate\Validation\ValidationException;
use Modules\Core\Overrides\Command;
use Modules\ERP\Casts\EInvoiceSubmissionStatus;
use Modules\ERP\Contracts\EInvoiceProvider;
use Modules\ERP\Models\EInvoiceSubmission;
use Modules\ERP\Services\EInvoice\EInvoiceSubmissionService;
use Override;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Throwable;

final class EInvoiceRefreshStatusesCommand extends Command
{
    #[Override]
    protected $signature = 'erp:einvoice:refresh-statuses
        {--company= : Restrict polling to one company id}
        {--provider= : Restrict polling to one provider code; defaults to the configured provider}
        {--limit=50 : Maximum submissions to refresh}
        {--dry-run : Show matching submissions without contacting the provider}';

    #[Override]
    protected $description = 'Refresh open ERP e-invoice submissions through the configured provider <fg=green>(Modules\ERP)</fg=green>';

    public function __construct(
        private readonly EInvoiceSubmissionService $submission_service,
        private readonly EInvoiceProvider $provider,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $provider_code = $this->providerCodeOption() ?: $this->provider->code();
        $limit = max(1, min(500, (int) $this->option('limit')));

        $query = EInvoiceSubmission::query()
            ->where('provider_code', $provider_code)
            ->whereIn('status', [
                EInvoiceSubmissionStatus::Queued->value,
                EInvoiceSubmissionStatus::Submitted->value,
            ])
            ->oldest('id')
            ->limit($limit);

        $company_id = $this->option('company');

        if (is_numeric($company_id)) {
            $query->where('company_id', (int) $company_id);
        }

        $submissions = $query->get();

        if ((bool) $this->option('dry-run')) {
            $this->info(sprintf('Matched %d open e-invoice submissions for provider [%s].', $submissions->count(), $provider_code));

            return BaseCommand::SUCCESS;
        }

        $refreshed = 0;
        $failed = 0;

        foreach ($submissions as $submission) {
            try {
                $this->submission_service->refresh($submission);
                $refreshed++;
            } catch (ValidationException $exception) {
                $failed++;
                $this->warn(sprintf('Submission %d skipped: %s', $submission->id, $exception->getMessage()));
            } catch (Throwable $exception) {
                $failed++;
                $this->error(sprintf('Submission %d failed: %s', $submission->id, $exception->getMessage()));
            }
        }

        $this->info(sprintf('Refreshed %d e-invoice submissions; %d failed.', $refreshed, $failed));

        return $failed === 0 ? BaseCommand::SUCCESS : BaseCommand::FAILURE;
    }

    private function providerCodeOption(): ?string
    {
        $provider = $this->option('provider');

        return is_string($provider) && mb_trim($provider) !== '' ? $provider : null;
    }
}
