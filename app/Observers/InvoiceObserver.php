<?php

declare(strict_types=1);

namespace Modules\ERP\Observers;

use Modules\ERP\Models\Invoice;
use Modules\ERP\Services\Accounting\InvoicePostingService;

final class InvoiceObserver
{
    public function __construct(
        private readonly InvoicePostingService $invoice_posting_service,
    ) {}

    public function saving(Invoice $invoice): void
    {
        if (! $invoice->exists || ! $invoice->isDirty('posted_at')) {
            return;
        }

        if ($invoice->posted_at === null && $invoice->getOriginal('posted_at') !== null) {
            $this->invoice_posting_service->unpost($invoice);

            return;
        }

        if ($invoice->posted_at !== null && $invoice->getOriginal('posted_at') === null) {
            $this->invoice_posting_service->post($invoice);
        }
    }
}
