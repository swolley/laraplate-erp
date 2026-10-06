<?php

declare(strict_types=1);

namespace Modules\ERP\Listeners;

use Modules\ERP\Enums\StockReservationState;
use Modules\ERP\Events\SalesOrderConfirmed;
use Modules\ERP\Exceptions\InsufficientStockException;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Models\StockReservation;
use Modules\ERP\Services\Inventory\StockReservationService;
use Modules\ERP\Support\Decimal;

/**
 * Holds stock for every item-backed line of a sales order the moment it is confirmed.
 *
 * Runs synchronously on purpose: a confirmation that cannot be covered must fail, so the
 * {@see InsufficientStockException} is left to bubble out of the model event and aborts the
 * confirm. The per-line reservations share one transaction, so a failure on a later line leaves
 * no hold behind on the earlier ones (a savepoint when the save already runs inside one).
 *
 * A live soft hold the line already owns is promoted to hard first; only the quantity that is
 * still not hard-reserved is reserved afresh, which re-validates availability. Lines without an
 * item (digital goods) reserve nothing.
 */
final readonly class ReserveStockForConfirmedSalesOrder
{
    public const string SOURCE_TYPE = 'erp.sales_order_line';

    public function __construct(private StockReservationService $reservations) {}

    /**
     * @throws InsufficientStockException When a line's remaining quantity exceeds the available stock.
     */
    public function handle(SalesOrderConfirmed $event): void
    {
        $order = $event->salesOrder;

        $lines = $order->lines()
            ->whereNotNull('item_id')
            ->orderBy('id')
            ->get();

        if ($lines->isEmpty()) {
            return;
        }

        $order->getConnection()->transaction(function () use ($order, $lines): void {
            foreach ($lines as $line) {
                $this->reserveLine($order->company_id, $line);
            }
        });
    }

    private function reserveLine(int $companyId, SalesOrderLine $line): void
    {
        $this->reservations->promoteToHard(self::SOURCE_TYPE, $line->id, $companyId);

        $remaining = Decimal::sub($line->qty_ordered, $this->hardReserved($companyId, $line->id));

        if (Decimal::isZero($remaining) || Decimal::isNegative($remaining)) {
            return;
        }

        $this->reservations->reserve(
            $companyId,
            $line->item_id,
            $remaining,
            StockReservationState::Hard,
            self::SOURCE_TYPE,
            $line->id,
        );
    }

    private function hardReserved(int $companyId, int $lineId): string
    {
        $total = '0.0000';

        $quantities = StockReservation::query()
            ->where('company_id', $companyId)
            ->where('source_type', self::SOURCE_TYPE)
            ->where('source_id', $lineId)
            ->where('state', StockReservationState::Hard->value)
            ->pluck('quantity');

        foreach ($quantities as $quantity) {
            $total = Decimal::add($total, (string) $quantity);
        }

        return $total;
    }
}
