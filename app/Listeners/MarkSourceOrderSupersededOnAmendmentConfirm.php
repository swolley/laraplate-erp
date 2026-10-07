<?php

declare(strict_types=1);

namespace Modules\ERP\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Events\SalesOrderConfirmed;
use Modules\ERP\Models\SalesOrder;
use Throwable;

/**
 * Supersedes the source order when its amendment is confirmed: the confirmed order is an amendment
 * (it carries `amends_sales_order_id`), so the order it amends is now obsolete and is marked
 * {@see SalesOrderStatus::Amended}. That flag is what the evasion service reads to refuse any
 * further delivery or invoice of the superseded source.
 *
 * Only a live source is transitioned, from `Confirmed` or `PartiallyEvased`; any other status
 * (already cancelled, already amended, still draft) is left untouched. The status move is allowed
 * even though the source header is locked, because status is not one of the locked key fields; and
 * setting `Amended` fires neither {@see SalesOrderConfirmed} nor `SalesOrderCancelled`, so no
 * further cascade runs.
 *
 * Best effort, mirroring the other confirm listeners: the amendment is already persisted as
 * confirmed when this runs, so a missing source or a failed save is logged and never thrown — the
 * confirm must not be broken by the supersede. This transition is independent of the reservation
 * release that {@see ReserveStockForConfirmedSalesOrder} performs for the same event; order between
 * the two listeners does not matter.
 */
final readonly class MarkSourceOrderSupersededOnAmendmentConfirm
{
    public function handle(SalesOrderConfirmed $event): void
    {
        $order = $event->salesOrder;

        if ($order->amends_sales_order_id === null) {
            return;
        }

        try {
            $source = SalesOrder::query()->whereKey($order->amends_sales_order_id)->first();

            if ($source === null) {
                Log::warning('An amendment confirmed but its amended source order could not be loaded to mark it superseded.', [
                    'amends_sales_order_id' => $order->amends_sales_order_id,
                    'amendment_sales_order_id' => $order->id,
                ]);

                return;
            }

            if (! in_array($source->status, [
                SalesOrderStatus::Confirmed,
                SalesOrderStatus::PartiallyEvased,
            ], true)) {
                return;
            }

            $source->update(['status' => SalesOrderStatus::Amended]);
        } catch (Throwable $exception) {
            Log::error('Marking the amended source order superseded failed; the amendment confirm proceeds.', [
                'amends_sales_order_id' => $order->amends_sales_order_id,
                'amendment_sales_order_id' => $order->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
