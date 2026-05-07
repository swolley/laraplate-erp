<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Purchasing;

use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\MatchStatus;
use Modules\ERP\Models\GoodsReceiptLine;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\PurchaseOrderLine;

/**
 * Validates purchase invoice lines against PO and GR lines using configurable tolerances.
 */
final class ThreeWayMatchService
{
    private const float DEFAULT_PRICE_TOLERANCE = 0.0;

    private const float DEFAULT_QTY_TOLERANCE = 0.0;

    /**
     * @return array{status: MatchStatus, discrepancies: array<string, mixed>}
     */
    public function validate(
        InvoiceLine $invoice_line,
        float $price_tolerance_percent = self::DEFAULT_PRICE_TOLERANCE,
        float $qty_tolerance_percent = self::DEFAULT_QTY_TOLERANCE,
        bool $force = false,
    ): array {
        $discrepancies = [];
        $has_breach = false;

        $po_line = $invoice_line->purchase_order_line_id !== null
            ? PurchaseOrderLine::query()->find($invoice_line->purchase_order_line_id)
            : null;

        $gr_line = $invoice_line->goods_receipt_line_id !== null
            ? GoodsReceiptLine::query()->find($invoice_line->goods_receipt_line_id)
            : null;

        if ($po_line === null && $gr_line === null) {
            return [
                'status' => MatchStatus::Unmatched,
                'discrepancies' => ['reason' => 'No PO or GR line linked.'],
            ];
        }

        if ($po_line !== null) {
            $price_diff = $this->percentDiff((float) $invoice_line->unit_price, (float) $po_line->unit_price);

            if ($price_diff > $price_tolerance_percent) {
                $has_breach = true;
            }

            if ($price_diff > 0) {
                $discrepancies['po_price'] = [
                    'expected' => (string) $po_line->unit_price,
                    'actual' => (string) $invoice_line->unit_price,
                    'diff_percent' => round($price_diff, 4),
                    'within_tolerance' => $price_diff <= $price_tolerance_percent,
                ];
            }

            $qty_diff = $this->percentDiff((float) $invoice_line->quantity, (float) $po_line->qty_ordered);

            if ($qty_diff > $qty_tolerance_percent) {
                $has_breach = true;
            }

            if ($qty_diff > 0) {
                $discrepancies['po_qty'] = [
                    'expected' => (string) $po_line->qty_ordered,
                    'actual' => (string) $invoice_line->quantity,
                    'diff_percent' => round($qty_diff, 4),
                    'within_tolerance' => $qty_diff <= $qty_tolerance_percent,
                ];
            }
        }

        if ($gr_line !== null) {
            $qty_diff = $this->percentDiff((float) $invoice_line->quantity, (float) $gr_line->qty_received);

            if ($qty_diff > $qty_tolerance_percent) {
                $has_breach = true;
            }

            if ($qty_diff > 0) {
                $discrepancies['gr_qty'] = [
                    'expected' => (string) $gr_line->qty_received,
                    'actual' => (string) $invoice_line->quantity,
                    'diff_percent' => round($qty_diff, 4),
                    'within_tolerance' => $qty_diff <= $qty_tolerance_percent,
                ];
            }
        }

        if ($has_breach && ! $force) {
            throw ValidationException::withMessages([
                'three_way_match' => [
                    'Three-way match failed: discrepancies exceed configured tolerances.',
                ],
            ]);
        }

        if ($has_breach) {
            return ['status' => MatchStatus::Forced, 'discrepancies' => $discrepancies];
        }

        if (! empty($discrepancies)) {
            return ['status' => MatchStatus::Tolerance, 'discrepancies' => $discrepancies];
        }

        return ['status' => MatchStatus::Matched, 'discrepancies' => []];
    }

    private function percentDiff(float $actual, float $expected): float
    {
        if (abs($expected) < 0.0000001) {
            return abs($actual) < 0.0000001 ? 0.0 : 100.0;
        }

        return abs(($actual - $expected) / $expected) * 100.0;
    }
}
