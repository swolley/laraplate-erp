<?php

declare(strict_types=1);

namespace Modules\ERP\Services\SalesOrders;

use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Services\Inventory\StockReservationService;
use Modules\ERP\Support\Decimal;

final class SalesOrderEvasionService
{
    /**
     * Opaque reservation source alias for a sales order line (never resolved by the reservation service).
     */
    private const string RESERVATION_SOURCE = 'erp.sales_order_line';

    public function __construct(
        private readonly StockReservationService $reservations,
    ) {}

    /**
     * @param  array<int, numeric-string|float|int>  $line_quantities
     */
    public function registerDelivery(SalesOrder $sales_order, array $line_quantities): void
    {
        $this->applyQuantities($sales_order, $line_quantities, 'delivery');
    }

    /**
     * @param  array<int, numeric-string|float|int>  $line_quantities
     */
    public function unregisterDelivery(SalesOrder $sales_order, array $line_quantities): void
    {
        $this->applyQuantities($sales_order, $line_quantities, 'delivery_reversal');
    }

    /**
     * @param  array<int, numeric-string|float|int>  $line_quantities
     */
    public function registerInvoice(SalesOrder $sales_order, array $line_quantities): void
    {
        $this->applyQuantities($sales_order, $line_quantities, 'invoice');
    }

    /**
     * @param  array<int, numeric-string|float|int>  $line_quantities
     */
    public function unregisterInvoice(SalesOrder $sales_order, array $line_quantities): void
    {
        $this->applyQuantities($sales_order, $line_quantities, 'invoice_reversal');
    }

    /**
     * @param  array<int, numeric-string|float|int>  $line_quantities
     */
    private function applyQuantities(SalesOrder $sales_order, array $line_quantities, string $mode): void
    {
        foreach ($line_quantities as $line_id => $qty) {
            /** @var SalesOrderLine|null $line */
            $line = $sales_order->lines()->find($line_id);

            if ($line === null) {
                continue;
            }

            $quantity = (float) $qty;

            if ($quantity <= 0) {
                continue;
            }

            $shipped = '0.0000';

            if ($mode === 'delivery') {
                $previous_delivered = $this->formatQuantity((float) $line->qty_delivered);
                $line->qty_delivered = $this->formatQuantity(min((float) $line->qty_ordered, (float) $line->qty_delivered + $quantity));
                $shipped = Decimal::sub($line->qty_delivered, $previous_delivered);
            } elseif ($mode === 'delivery_reversal') {
                $line->qty_delivered = $this->formatQuantity(max(0.0, (float) $line->qty_delivered - $quantity));
            } elseif ($mode === 'invoice_reversal') {
                $line->qty_invoiced = $this->formatQuantity(max(0.0, (float) $line->qty_invoiced - $quantity));
            } else {
                $line->qty_invoiced = $this->formatQuantity(min((float) $line->qty_ordered, (float) $line->qty_invoiced + $quantity));
            }

            $line->status = $this->lineStatusFromQuantities($line);

            $line->save();

            if ($mode === 'delivery' && $line->item_id !== null) {
                $this->consumeReservation($line, $shipped);
            }
        }

        $this->syncHeaderStatus($sales_order->fresh(['lines']) ?? $sales_order);
    }

    /**
     * Closes the hard reservation for the quantity actually shipped, clamped to what the line still
     * holds: a partially backordered line reserved less than it ships, so consuming `qty_delivered`
     * blindly would over-consume. The backorder remainder was never reserved and stays unconsumed.
     */
    private function consumeReservation(SalesOrderLine $line, string $shipped): void
    {
        if (Decimal::isZero($shipped)) {
            return;
        }

        $reserved = $this->reservations->reservedQuantity(self::RESERVATION_SOURCE, $line->id);
        $to_consume = Decimal::isNegative(Decimal::sub($shipped, $reserved)) ? $shipped : $reserved;

        if (Decimal::isZero($to_consume)) {
            return;
        }

        $this->reservations->consume(self::RESERVATION_SOURCE, $line->id, $to_consume);
    }

    private function lineStatusFromQuantities(SalesOrderLine $line): SalesOrderLineStatus
    {
        if ($line->qty_invoiced >= $line->qty_ordered && $line->qty_delivered >= $line->qty_ordered) {
            return SalesOrderLineStatus::FullyEvased;
        }

        if ($line->qty_delivered > 0 || $line->qty_invoiced > 0) {
            return SalesOrderLineStatus::PartiallyEvased;
        }

        return SalesOrderLineStatus::Open;
    }

    private function syncHeaderStatus(SalesOrder $sales_order): void
    {
        $lines = $sales_order->lines;

        if ($lines->isEmpty()) {
            return;
        }

        $all_fully_evased = $lines->every(
            static fn (SalesOrderLine $line): bool => $line->status === SalesOrderLineStatus::FullyEvased,
        );

        if ($all_fully_evased) {
            $sales_order->status = SalesOrderStatus::FullyEvased;
            $sales_order->saveQuietly();

            return;
        }

        $has_progress = $lines->contains(
            static fn (SalesOrderLine $line): bool => $line->qty_delivered > 0 || $line->qty_invoiced > 0,
        );

        if ($has_progress) {
            $sales_order->status = SalesOrderStatus::PartiallyEvased;
            $sales_order->saveQuietly();

            return;
        }

        $sales_order->status = SalesOrderStatus::Confirmed;
        $sales_order->saveQuietly();
    }

    /**
     * @return numeric-string
     */
    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 4, '.', '');
    }
}
