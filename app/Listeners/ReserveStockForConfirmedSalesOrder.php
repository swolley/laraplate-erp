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
 * Holds stock for the item-backed lines of a sales order the moment it is confirmed, best effort.
 *
 * It never blocks the confirmation. Each line reserves `min(quantity not yet hard-reserved,
 * available)`, clamped before calling the service so {@see InsufficientStockException} is not
 * triggered; the part that stock cannot cover stays unreserved as backorder or make-to-order (MES
 * plans production for it off the same event). Overselling stays impossible because the listener
 * never holds more than is on hand.
 *
 * A live soft hold the line already owns is promoted to hard first. Lines without an item
 * (digital goods) reserve nothing.
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

        $lines = $order->lines()
            ->whereNotNull('item_id')
            ->orderBy('id')
            ->get();

        foreach ($lines as $line) {
            $this->reserveLine($order->company_id, $line);
        }
    }

    private function reserveLine(int $companyId, SalesOrderLine $line): void
    {
        $this->reservations->promoteToHard(self::SOURCE_TYPE, $line->id, $companyId);

        $remaining = Decimal::sub($line->qty_ordered, $this->hardReserved($companyId, $line->id));

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
            }
        }
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
