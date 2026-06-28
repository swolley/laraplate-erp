<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;

/**
 * Compacts or expands invoice lines based on their delivery note line pivot linkage.
 *
 * In "expanded" mode each DDT line maps 1:1 to an invoice line.
 * In "compact" mode invoice lines are grouped by item_id + unit_price and the
 * pivot tracks which DDT lines contribute to each aggregated invoice line.
 */
final class InvoiceCompactionService
{
    /**
     * Compact an expanded invoice: merge lines sharing the same item_id and unit_price.
     */
    public function compact(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $lines = InvoiceLine::query()
                ->where('invoice_id', $invoice->id)
                ->with('delivery_note_lines')
                ->orderBy('line_no')
                ->get();

            if ($lines->count() <= 1) {
                return;
            }

            $groups = $lines->groupBy(
                static fn (InvoiceLine $line): string => (string) ($line->item_id ?? '') . '|' . $line->unit_price,
            );

            $line_no = 0;

            foreach ($groups as $group_lines) {
                $line_no++;

                $keeper = $group_lines->first();

                if ($keeper === null) {
                    continue;
                }

                if ($group_lines->count() === 1) {
                    if ($line_no !== $keeper->line_no) {
                        $keeper->update(['line_no' => $line_no]);
                    }

                    continue;
                }

                $total_quantity = $this->formatQuantity(
                    $group_lines->sum(static fn (InvoiceLine $line): float => (float) $line->quantity),
                );
                $all_pivot_data = [];

                foreach ($group_lines as $line) {
                    foreach ($line->delivery_note_lines as $dnl) {
                        $all_pivot_data[$this->modelId($dnl)] = [
                            'quantity' => $this->pivotQuantity($dnl),
                        ];
                    }
                }

                $keeper->update([
                    'line_no' => $line_no,
                    'quantity' => $total_quantity,
                ]);

                $keeper->delivery_note_lines()->sync($all_pivot_data);

                foreach ($group_lines->skip(1) as $duplicate) {
                    $duplicate->delivery_note_lines()->detach();
                    $duplicate->delete();
                }
            }
        });
    }

    /**
     * Expand a compacted invoice: split lines back so each DDT line maps 1:1.
     */
    public function expand(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $lines = InvoiceLine::query()
                ->where('invoice_id', $invoice->id)
                ->with('delivery_note_lines')
                ->orderBy('line_no')
                ->get();

            $line_no = 0;

            foreach ($lines as $line) {
                $dn_lines = $line->delivery_note_lines;

                if ($dn_lines->count() <= 1) {
                    $line_no++;

                    if ($line_no !== $line->line_no) {
                        $line->update(['line_no' => $line_no]);
                    }

                    continue;
                }

                $is_first = true;

                foreach ($dn_lines as $dnl) {
                    $line_no++;
                    $pivot_qty = $this->pivotQuantity($dnl);

                    if ($is_first) {
                        $line->update([
                            'line_no' => $line_no,
                            'quantity' => $pivot_qty,
                        ]);
                        $line->delivery_note_lines()->sync([$this->modelId($dnl) => ['quantity' => $pivot_qty]]);
                        $is_first = false;

                        continue;
                    }

                    $new_line = $line->replicate(['id', 'line_no', 'quantity']);
                    $new_line->line_no = $line_no;
                    $new_line->quantity = $pivot_qty;
                    $new_line->save();

                    $new_line->delivery_note_lines()->attach($this->modelId($dnl), ['quantity' => $pivot_qty]);
                }
            }
        });
    }

    private function modelId(DeliveryNoteLine $line): int
    {
        return is_int($line->id) ? $line->id : (int) $line->id;
    }

    /**
     * @return numeric-string
     */
    private function pivotQuantity(DeliveryNoteLine $dn_line): string
    {
        $pivot = $dn_line->pivot;

        if ($pivot === null) {
            throw ValidationException::withMessages([
                'lines' => ['Delivery note link is missing pivot data on invoice line.'],
            ]);
        }

        $quantity = $pivot->getAttributes()['quantity'] ?? null;

        if (! is_numeric($quantity)) {
            throw ValidationException::withMessages([
                'lines' => ['Delivery note link quantity is invalid on invoice line.'],
            ]);
        }

        return $this->formatQuantity((float) $quantity);
    }

    /**
     * @return numeric-string
     */
    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 4, '.', '');
    }
}
