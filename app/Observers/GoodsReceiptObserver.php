<?php

declare(strict_types=1);

namespace Modules\ERP\Observers;

use Modules\ERP\Models\GoodsReceipt;
use Modules\ERP\Services\Inventory\GoodsReceiptInventoryService;

final class GoodsReceiptObserver
{
    public function __construct(
        private readonly GoodsReceiptInventoryService $goods_receipt_inventory_service,
    ) {}

    /**
     * Runs inventory posting inside the same save cycle as `posted_at` so a
     * validation or stock failure aborts persisting the document as posted.
     */
    public function saving(GoodsReceipt $goods_receipt): void
    {
        if (! $goods_receipt->exists) {
            return;
        }

        if (! $goods_receipt->isDirty('posted_at')) {
            return;
        }

        if ($goods_receipt->posted_at === null) {
            return;
        }

        if ($goods_receipt->getOriginal('posted_at') !== null) {
            return;
        }

        if ($goods_receipt->inventory_posted_at !== null) {
            return;
        }

        $this->goods_receipt_inventory_service->postInventory($goods_receipt);
    }
}
