<?php

declare(strict_types=1);

namespace Modules\ERP\Services\SalesOrders;

use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;

final class SalesOrderEvasionService
{
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

            if ($mode === 'delivery') {
                $line->qty_delivered = $this->formatQuantity(min((float) $line->qty_ordered, (float) $line->qty_delivered + $quantity));
            } elseif ($mode === 'delivery_reversal') {
                $line->qty_delivered = $this->formatQuantity(max(0.0, (float) $line->qty_delivered - $quantity));
            } elseif ($mode === 'invoice_reversal') {
                $line->qty_invoiced = $this->formatQuantity(max(0.0, (float) $line->qty_invoiced - $quantity));
            } else {
                $line->qty_invoiced = $this->formatQuantity(min((float) $line->qty_ordered, (float) $line->qty_invoiced + $quantity));
            }

            $line->status = $this->lineStatusFromQuantities($line);
            $line->save();
        }

        $this->syncHeaderStatus($sales_order->fresh(['lines']));
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

    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 4, '.', '');
    }
}
