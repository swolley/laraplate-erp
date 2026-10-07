<?php

declare(strict_types=1);

namespace Modules\ERP\Services\SalesOrders;

use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Services\Inventory\StockReservationService;
use Modules\ERP\Support\Decimal;

final readonly class SalesOrderEvasionService
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
     * The header status implied by the order's line quantities: {@see SalesOrderStatus::FullyEvased}
     * when every line is fully evased, {@see SalesOrderStatus::PartiallyEvased} when any line has
     * been delivered or invoiced, otherwise {@see SalesOrderStatus::Confirmed}. Returns null when the
     * order has no lines, so the caller leaves the status untouched. Does not persist anything.
     */
    public function statusFromLineQuantities(SalesOrder $sales_order): ?SalesOrderStatus
    {
        $lines = $sales_order->lines;

        if ($lines->isEmpty()) {
            return null;
        }

        $all_fully_evased = $lines->every(
            static fn (SalesOrderLine $line): bool => $line->status === SalesOrderLineStatus::FullyEvased,
        );

        if ($all_fully_evased) {
            return SalesOrderStatus::FullyEvased;
        }

        $has_progress = $lines->contains(
            static fn (SalesOrderLine $line): bool => $line->qty_delivered > 0 || $line->qty_invoiced > 0,
        );

        if ($has_progress) {
            return SalesOrderStatus::PartiallyEvased;
        }

        return SalesOrderStatus::Confirmed;
    }

    /**
     * @param  array<int, numeric-string|float|int>  $line_quantities
     */
    private function applyQuantities(SalesOrder $sales_order, array $line_quantities, string $mode): void
    {
        $this->guardForwardEvasion($sales_order, $mode);

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
     * Blocks forward evasion of an order that is no longer live: a superseded ({@see SalesOrderStatus::Amended})
     * or {@see SalesOrderStatus::Cancelled} order must not ship or invoice, and because `applyQuantities`
     * would otherwise recompute and overwrite the header status from line quantities, delivering it would
     * silently un-supersede it. Reversals stay allowed so a prior movement can still be undone.
     */
    private function guardForwardEvasion(SalesOrder $sales_order, string $mode): void
    {
        if ($mode !== 'delivery' && $mode !== 'invoice') {
            return;
        }

        if (! in_array($sales_order->status, [SalesOrderStatus::Amended, SalesOrderStatus::Cancelled], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => [sprintf(
                '%s %s sales order cannot be %s.',
                $sales_order->status === SalesOrderStatus::Amended ? 'An' : 'A',
                $sales_order->status->value,
                $mode === 'delivery' ? 'delivered' : 'invoiced',
            )],
        ]);
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

    /**
     * Recomputes the header status from the current line quantities, unless the order sits in a
     * terminal lifecycle state. An {@see SalesOrderStatus::Amended} order (superseded by a confirmed
     * amendment) or a {@see SalesOrderStatus::Cancelled} one keeps that status even while a reversal
     * rewinds its line quantities: recomputing would silently un-supersede it and reopen it to
     * evasion and re-amendment. The line quantity changes are already persisted by the caller; only
     * the header is held.
     */
    private function syncHeaderStatus(SalesOrder $sales_order): void
    {
        if (in_array($sales_order->status, [SalesOrderStatus::Amended, SalesOrderStatus::Cancelled], true)) {
            return;
        }

        $status = $this->statusFromLineQuantities($sales_order);

        if ($status === null) {
            return;
        }

        $sales_order->status = $status;
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
