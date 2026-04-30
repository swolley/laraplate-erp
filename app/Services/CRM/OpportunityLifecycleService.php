<?php

declare(strict_types=1);

namespace Modules\ERP\Services\CRM;

use Modules\ERP\Casts\OpportunityStatus;
use Modules\ERP\Casts\QuoteStatus;
use Modules\ERP\Models\Opportunity;
use Modules\ERP\Models\Quotation;

final class OpportunityLifecycleService
{
    public function markWonFromQuotation(Quotation $quotation): void
    {
        if ($quotation->opportunity_id === null) {
            return;
        }

        if ($quotation->status !== QuoteStatus::ACCEPTED) {
            return;
        }

        /** @var Opportunity|null $opportunity */
        $opportunity = Opportunity::query()->find($quotation->opportunity_id);

        if ($opportunity === null) {
            return;
        }

        if ($opportunity->status === OpportunityStatus::WON && $opportunity->won_at !== null) {
            return;
        }

        $opportunity->status = OpportunityStatus::WON;
        $opportunity->won_at ??= now();
        $opportunity->saveQuietly();
    }
}
