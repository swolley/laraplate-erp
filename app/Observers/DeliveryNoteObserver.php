<?php

declare(strict_types=1);

namespace Modules\ERP\Observers;

use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Services\Inventory\DeliveryNoteInventoryService;

final class DeliveryNoteObserver
{
    public function __construct(
        private readonly DeliveryNoteInventoryService $delivery_note_inventory_service,
    ) {}

    /**
     * Runs inventory posting inside the same save cycle as `posted_at` so a
     * validation or stock failure aborts persisting the document as posted.
     */
    public function saving(DeliveryNote $delivery_note): void
    {
        if (! $delivery_note->exists) {
            return;
        }

        if (! $delivery_note->isDirty('posted_at')) {
            return;
        }

        if ($delivery_note->posted_at === null) {
            return;
        }

        if ($delivery_note->getOriginal('posted_at') !== null) {
            return;
        }

        if ($delivery_note->inventory_posted_at !== null) {
            return;
        }

        $this->delivery_note_inventory_service->postInventory($delivery_note);
    }
}
