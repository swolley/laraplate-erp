<?php

declare(strict_types=1);

namespace Modules\ERP\Listeners;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Enums\StockReservationState;
use Modules\ERP\Events\SalesOrderConfirmed;
use Modules\ERP\Exceptions\InsufficientStockException;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Services\Inventory\StockReservationService;
use Modules\ERP\Support\Decimal;
use Throwable;

/**
 * Holds stock for the item-backed lines of a sales order the moment it is confirmed, best effort.
 *
 * It never blocks the confirmation. Each line reserves `min(quantity still owed and not yet
 * hard-reserved, available)`, clamped before calling the service so {@see InsufficientStockException} is not
 * triggered; the part that stock cannot cover stays unreserved as backorder or make-to-order (MES
 * plans production for it off the same event). Overselling stays impossible because the listener
 * never holds more than is on hand.
 *
 * A live soft hold the line already owns is promoted to hard first. Lines without an item
 * (digital goods) reserve nothing.
 *
 * No exception from `reserve()` escapes: the order is already persisted as confirmed when this
 * runs (the model save has no wrapping transaction), so throwing would leave it half-confirmed and
 * could skip the other listeners. A stock shortfall that lost a race and a lock that stays
 * contended end as a WARNING log; an item that is not the order company's is a data anomaly and
 * ends as an ERROR log. Either way the line is left unreserved.
 */
final readonly class ReserveStockForConfirmedSalesOrder
{
    public const string SOURCE_TYPE = 'erp.sales_order_line';

    /**
     * Attempts per line when a concurrent reserver takes stock between the availability read and
     * the reservation. Each attempt re-reads availability, so the clamp stays honest.
     */
    private const int MAX_ATTEMPTS = 3;

    public function __construct(private StockReservationService $reservations) {}

    public function handle(SalesOrderConfirmed $event): void
    {
        $order = $event->salesOrder;

        // An amendment confirming supersedes its source order: free the source's hard holds first
        // so the stock they pinned is available again, then reserve the amendment's own lines
        // against it. Releasing before reserving is what lets the amendment actually take the
        // remaining quantity; reserving first would see ~zero availability and hold nothing.
        if ($order->amends_sales_order_id !== null) {
            $this->releaseAmendedSourceHolds($order->amends_sales_order_id);
        }

        $this->reserveOrderLines($order);
    }

    /**
     * Reserves every item-backed line of the order best effort, holding `min(quantity still owed and
     * not yet reserved, available)` per line. Shared by the confirm path and by the revert that
     * restores a source order when its amendment is cancelled (where already delivered lines must
     * re-reserve only what is still owed).
     */
    public function reserveOrderLines(SalesOrder $order): void
    {
        $lines = $order->lines()
            ->whereNotNull('item_id')
            ->orderBy('id')
            ->get();

        foreach ($lines as $line) {
            $this->reserveLine($order->company_id, $line);
        }
    }

    /**
     * Releases every live hard hold the amended source order's lines still carry, best effort. The
     * source is in the same company, so the global company scope resolves it; a missing source or a
     * release failure is logged and never thrown, so it cannot break the amendment's confirmation.
     */
    private function releaseAmendedSourceHolds(int $sourceOrderId): void
    {
        try {
            $source = SalesOrder::query()
                ->whereKey($sourceOrderId)
                ->with('lines')
                ->first();

            if ($source === null) {
                Log::warning('An amendment confirmed but its amended source order could not be loaded to release its reservations.', [
                    'amends_sales_order_id' => $sourceOrderId,
                ]);

                return;
            }

            foreach ($source->lines as $sourceLine) {
                $this->reservations->release(self::SOURCE_TYPE, $sourceLine->id);
            }
        } catch (Throwable $exception) {
            Log::error('Releasing the amended source order reservations failed; the amendment confirm proceeds.', [
                'amends_sales_order_id' => $sourceOrderId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function reserveLine(int $companyId, SalesOrderLine $line): void
    {
        $this->reservations->promoteToHard(self::SOURCE_TYPE, $line->id, $companyId);

        // Reserve only what the line still owes: its ordered quantity less what has already shipped
        // (a delivered quantity is terminal and was consumed, never re-held) less what it still
        // holds live. At confirm time nothing is delivered, so this is the full ordered quantity;
        // on a source reverted from a cancelled amendment it is the undelivered remainder.
        $owed = Decimal::sub($line->qty_ordered, $line->qty_delivered);
        $remaining = Decimal::sub($owed, $this->reservations->reservedQuantity(self::SOURCE_TYPE, $line->id));

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $quantity = $this->clampToAvailable($remaining, $this->reservations->available($companyId, $line->item_id));

            if ($quantity === null) {
                return;
            }

            try {
                $this->reservations->reserve(
                    $companyId,
                    $line->item_id,
                    $quantity,
                    StockReservationState::Hard,
                    self::SOURCE_TYPE,
                    $line->id,
                );

                return;
            } catch (InsufficientStockException) {
                continue;
            } catch (LockTimeoutException) {
                // The service already waited its whole lock window; retrying would stall the confirm further.
                $this->logUnreserved($companyId, $line, 'The item lock stayed contended.');

                return;
            } catch (ValidationException $exception) {
                Log::error('A sales order line could not be reserved: the reservation was rejected as invalid (data anomaly, e.g. an item of another company).', $this->logContext($companyId, $line) + [
                    'errors' => $exception->errors(),
                ]);

                return;
            }
        }

        $this->logUnreserved($companyId, $line, 'Concurrent reservers kept taking the stock.');
    }

    private function logUnreserved(int $companyId, SalesOrderLine $line, string $reason): void
    {
        Log::warning('A sales order line was left unreserved by contention, not by a plain stock shortfall.', $this->logContext($companyId, $line) + [
            'reason' => $reason,
        ]);
    }

    /**
     * @return array{company_id: int, item_id: int, sales_order_line_id: int, sales_order_id: int}
     */
    private function logContext(int $companyId, SalesOrderLine $line): array
    {
        return [
            'company_id' => $companyId,
            'item_id' => $line->item_id,
            'sales_order_line_id' => $line->id,
            'sales_order_id' => $line->sales_order_id,
        ];
    }

    /**
     * The quantity to reserve: the smaller of `$remaining` and `$available`, or null when either
     * is not positive and there is nothing to hold.
     */
    private function clampToAvailable(string $remaining, string $available): ?string
    {
        $quantity = Decimal::isNegative(Decimal::sub($remaining, $available)) ? $remaining : $available;

        if (Decimal::isZero($quantity) || Decimal::isNegative($quantity)) {
            return null;
        }

        return $quantity;
    }
}
