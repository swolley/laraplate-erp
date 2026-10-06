<?php

declare(strict_types=1);

namespace Modules\ERP\Listeners;

use Modules\ERP\Events\SalesOrderCancelled;
use Modules\ERP\Services\Inventory\StockReservationService;

/**
 * Releases every stock reservation held for a sales order's lines the moment the order is cancelled.
 *
 * {@see StockReservationService::release()} is idempotent and keyed by the opaque
 * `(source_type, source_id)` pair, so item-less lines (which never reserved) and an order cancelled
 * twice both resolve to a no-op. Consumed quantities are terminal and are not touched.
 */
final readonly class ReleaseStockForCancelledSalesOrder
{
    public const string SOURCE_TYPE = 'erp.sales_order_line';

    public function __construct(private StockReservationService $reservations) {}

    public function handle(SalesOrderCancelled $event): void
    {
        $event->salesOrder->lines()
            ->pluck('id')
            ->each(function (int $lineId): void {
                $this->reservations->release(self::SOURCE_TYPE, $lineId);
            });
    }
}
