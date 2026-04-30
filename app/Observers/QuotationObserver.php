<?php

declare(strict_types=1);

namespace Modules\ERP\Observers;

use Modules\ERP\Models\Quotation;
use Modules\ERP\Services\CRM\OpportunityLifecycleService;

final class QuotationObserver
{
    public function __construct(
        private readonly OpportunityLifecycleService $opportunity_lifecycle_service,
    ) {}

    public function saved(Quotation $quotation): void
    {
        if (! $quotation->wasChanged('status') && $quotation->wasRecentlyCreated === false) {
            return;
        }

        $this->opportunity_lifecycle_service->markWonFromQuotation($quotation);
    }
}
