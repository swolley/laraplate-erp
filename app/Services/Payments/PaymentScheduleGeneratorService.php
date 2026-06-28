<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\PaymentScheduleLine;
use Modules\ERP\Models\PaymentTerm;

final class PaymentScheduleGeneratorService
{
    public function generate(Invoice $invoice, string $gross_total): void
    {
        $gross_total_float = (float) $gross_total;

        if ($gross_total_float < 0) {
            $gross_total = $this->round4(abs($gross_total_float));
        }

        DB::transaction(function () use ($invoice, $gross_total): void {
            $posted_at = $invoice->posted_at instanceof CarbonImmutable
                ? $invoice->posted_at
                : CarbonImmutable::parse($invoice->posted_at);

            $currency = (string) $invoice->currency;
            $fx_rate = '1.0000';

            if ($invoice->payment_term_id === null) {
                PaymentScheduleLine::query()->create([
                    'company_id' => $invoice->company_id,
                    'invoice_id' => $this->invoiceId($invoice),
                    'due_date' => $posted_at->toDateString(),
                    'amount_doc' => $gross_total,
                    'currency_doc' => $currency,
                    'amount_local' => $gross_total,
                    'currency_local' => $currency,
                    'fx_rate' => $fx_rate,
                    'paid_amount_doc' => '0.0000',
                    'paid_amount_local' => '0.0000',
                    'status' => PaymentScheduleStatus::Open,
                ]);

                return;
            }

            $payment_term = PaymentTerm::query()
                ->withoutGlobalScopes()
                ->findOrFail($invoice->payment_term_id);

            foreach ($payment_term->rate_lines as $rate_line) {
                $days = (int) ($rate_line['days'] ?? 0);
                $percent = (float) ($rate_line['percent'] ?? 0);

                $amount_doc = $this->round4((float) $gross_total * $percent / 100);
                $due_date = $posted_at->addDays($days)->toDateString();

                PaymentScheduleLine::query()->create([
                    'company_id' => $invoice->company_id,
                    'invoice_id' => $this->invoiceId($invoice),
                    'due_date' => $due_date,
                    'amount_doc' => $amount_doc,
                    'currency_doc' => $currency,
                    'amount_local' => $amount_doc,
                    'currency_local' => $currency,
                    'fx_rate' => $fx_rate,
                    'paid_amount_doc' => '0.0000',
                    'paid_amount_local' => '0.0000',
                    'status' => PaymentScheduleStatus::Open,
                ]);
            }
        });
    }

    public function removeAll(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $has_allocations = PaymentScheduleLine::query()
                ->where('invoice_id', $this->invoiceId($invoice))
                ->where('paid_amount_doc', '>', 0)
                ->exists();

            if ($has_allocations) {
                throw ValidationException::withMessages([
                    'payment_schedule' => ['Cannot remove schedule lines: one or more lines have allocations.'],
                ]);
            }

            PaymentScheduleLine::query()
                ->where('invoice_id', $this->invoiceId($invoice))
                ->delete();
        });
    }

    private function invoiceId(Invoice $invoice): int
    {
        return is_int($invoice->id) ? $invoice->id : (int) $invoice->id;
    }

    /**
     * @return numeric-string
     */
    private function round4(float $value): string
    {
        return number_format(round($value, 4), 4, '.', '');
    }
}
