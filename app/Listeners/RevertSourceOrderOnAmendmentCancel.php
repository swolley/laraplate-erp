<?php

declare(strict_types=1);

namespace Modules\ERP\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Events\SalesOrderCancelled;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Services\SalesOrders\SalesOrderEvasionService;
use Throwable;

/**
 * Reverts the amended source order when its amendment is cancelled: the cancelled order carries
 * `amends_sales_order_id`, so the order it superseded ({@see SalesOrderStatus::Amended}) is no
 * longer replaced and must come back to life. This is the exact inverse of
 * {@see MarkSourceOrderSupersededOnAmendmentConfirm}, which marked the source `Amended` when the
 * amendment confirmed.
 *
 * The source is recomputed from its own line quantities ({@see SalesOrderStatus::PartiallyEvased}
 * when any line has moved, otherwise {@see SalesOrderStatus::Confirmed}) and its lines are
 * re-reserved along the same best-effort path as a confirm, which re-holds only the undelivered
 * remainder of each line. The amendment's own holds are already released by
 * {@see ReleaseStockForCancelledSalesOrder} on the same event.
 *
 * Only a source still marked `Amended` is reverted; a draft amendment that never superseded it, or
 * a source cancelled on its own, is left untouched. `Amended` is a terminal status the evasion
 * service preserves, so the new status is set here with `saveQuietly`, which also skips the status
 * cascade: re-reserving is done directly rather than by re-firing {@see SalesOrderConfirmed}, which
 * would re-run the supersede and any production planning keyed off a genuine confirm.
 *
 * Best effort, mirroring the other lifecycle listeners: the amendment is already persisted as
 * cancelled when this runs, so a missing source or a failed save is logged and never thrown.
 */
final readonly class RevertSourceOrderOnAmendmentCancel
{
    public function __construct(
        private SalesOrderEvasionService $evasion,
        private ReserveStockForConfirmedSalesOrder $reservation,
    ) {}

    public function handle(SalesOrderCancelled $event): void
    {
        $amendment = $event->salesOrder;

        if ($amendment->amends_sales_order_id === null) {
            return;
        }

        try {
            $source = SalesOrder::query()
                ->whereKey($amendment->amends_sales_order_id)
                ->with('lines')
                ->first();

            if ($source === null) {
                Log::warning('An amendment was cancelled but its amended source order could not be loaded to revert it.', [
                    'amends_sales_order_id' => $amendment->amends_sales_order_id,
                    'amendment_sales_order_id' => $amendment->id,
                ]);

                return;
            }

            if ($source->status !== SalesOrderStatus::Amended) {
                return;
            }

            $status = $this->evasion->statusFromLineQuantities($source);

            if ($status === null) {
                return;
            }

            $source->status = $status;
            $source->saveQuietly();

            $this->reservation->reserveOrderLines($source);
        } catch (Throwable $exception) {
            Log::error('Reverting the amended source order after an amendment cancel failed; the cancel proceeds.', [
                'amends_sales_order_id' => $amendment->amends_sales_order_id,
                'amendment_sales_order_id' => $amendment->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
