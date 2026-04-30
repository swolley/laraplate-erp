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
     * @param  array<int, int>  $line_quantities
     */
    public function registerDelivery(SalesOrder $sales_order, array $line_quantities): void
    {
        $this->applyQuantities($sales_order, $line_quantities, 'delivery');
    }

    /**
     * @param  array<int, int>  $line_quantities
     */
    public function registerInvoice(SalesOrder $sales_order, array $line_quantities): void
    {
        $this->applyQuantities($sales_order, $line_quantities, 'invoice');
    }

    /**
     * @param  array<int, int>  $line_quantities
     */
    private function applyQuantities(SalesOrder $sales_order, array $line_quantities, string $mode): void
    {
        foreach ($line_quantities as $line_id => $qty) {
            /** @var SalesOrderLine|null $line */
            $line = $sales_order->lines()->find($line_id);

            if ($line === null || $qty <= 0) {
                continue;
            }

            if ($mode === 'delivery') {
                $line->qty_delivered = min($line->qty_ordered, $line->qty_delivered + $qty);
            } else {
                $line->qty_invoiced = min($line->qty_ordered, $line->qty_invoiced + $qty);
            }

            $line->status = $this->lineStatusFromQuantities($line);
            $line->save();
        }

        $this->syncHeaderStatus($sales_order->fresh(['lines']));
    }

    private function lineStatusFromQuantities(SalesOrderLine $line): SalesOrderLineStatus
    {
        if ($line->qty_invoiced >= $line->qty_ordered && $line->qty_delivered >= $line->qty_ordered) {
            return SalesOrderLineStatus::FULLY_EVASED;
        }

        if ($line->qty_delivered > 0 || $line->qty_invoiced > 0) {
            return SalesOrderLineStatus::PARTIALLY_EVASED;
        }

        return SalesOrderLineStatus::OPEN;
    }

    private function syncHeaderStatus(SalesOrder $sales_order): void
    {
        $lines = $sales_order->lines;

        if ($lines->isEmpty()) {
            return;
        }

        $all_fully_evased = $lines->every(
            static fn (SalesOrderLine $line): bool => $line->status === SalesOrderLineStatus::FULLY_EVASED
        );

        if ($all_fully_evased) {
            $sales_order->status = SalesOrderStatus::FULLY_EVASED;
            $sales_order->saveQuietly();

            return;
        }

        $has_progress = $lines->contains(
            static fn (SalesOrderLine $line): bool => $line->qty_delivered > 0 || $line->qty_invoiced > 0
        );

        if ($has_progress) {
            $sales_order->status = SalesOrderStatus::PARTIALLY_EVASED;
            $sales_order->saveQuietly();
        }
    }
}
