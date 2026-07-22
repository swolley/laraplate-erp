<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Models\Payment;
use Modules\ERP\Models\PaymentAllocation;
use Modules\ERP\Support\ConnectionScopedTransaction;
use Modules\ERP\Models\PaymentScheduleLine;

final class PaymentAllocationService
{
    /**
     * @param  array<int, string>  $allocations  [schedule_line_id => allocated_amount_doc]
     */
    public function allocate(Payment $payment, array $allocations): void
    {
        ConnectionScopedTransaction::run($payment, function () use ($payment, $allocations): void {
            foreach ($allocations as $schedule_line_id => $amount_doc) {
                $amount_doc_float = (float) $amount_doc;

                if ($amount_doc_float <= 0) {
                    throw ValidationException::withMessages([
                        'amount_doc' => ['Allocation amount must be greater than zero.'],
                    ]);
                }

                $schedule_line = PaymentScheduleLine::query()
                    ->whereKey($schedule_line_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $remaining = (float) $schedule_line->amount_doc - (float) $schedule_line->paid_amount_doc;

                if ($amount_doc_float > $remaining + 0.00005) {
                    throw ValidationException::withMessages([
                        'amount_doc' => ['Allocation exceeds remaining amount on schedule line.'],
                    ]);
                }

                $amount_local = $this->round4($amount_doc_float * (float) $schedule_line->fx_rate);

                PaymentAllocation::query()->create([
                    'payment_id' => $this->paymentId($payment),
                    'payment_schedule_line_id' => $schedule_line_id,
                    'allocated_amount_doc' => $this->round4($amount_doc_float),
                    'allocated_amount_local' => $amount_local,
                ]);

                $new_paid_doc = $this->round4((float) $schedule_line->paid_amount_doc + $amount_doc_float);
                $new_paid_local = $this->round4((float) $schedule_line->paid_amount_local + (float) $amount_local);

                $schedule_line->paid_amount_doc = $new_paid_doc;
                $schedule_line->paid_amount_local = $new_paid_local;
                $schedule_line->status = $this->resolveStatus((float) $new_paid_doc, (float) $schedule_line->amount_doc);

                if ($schedule_line->status === PaymentScheduleStatus::Paid) {
                    $schedule_line->paid_at = CarbonImmutable::now();
                }

                $schedule_line->save();
            }
        });
    }

    public function deallocate(PaymentAllocation $allocation): void
    {
        ConnectionScopedTransaction::run($allocation, function () use ($allocation): void {
            $schedule_line = PaymentScheduleLine::query()
                ->whereKey($allocation->payment_schedule_line_id)
                ->lockForUpdate()
                ->firstOrFail();

            $new_paid_doc = $this->round4(
                (float) $schedule_line->paid_amount_doc - (float) $allocation->allocated_amount_doc,
            );
            $new_paid_local = $this->round4(
                (float) $schedule_line->paid_amount_local - (float) $allocation->allocated_amount_local,
            );

            $schedule_line->paid_amount_doc = $new_paid_doc;
            $schedule_line->paid_amount_local = $new_paid_local;
            $schedule_line->status = $this->resolveStatus((float) $new_paid_doc, (float) $schedule_line->amount_doc);

            if ($schedule_line->status !== PaymentScheduleStatus::Paid) {
                $schedule_line->paid_at = null;
            }

            $schedule_line->save();

            $allocation->delete();
        });
    }

    private function resolveStatus(float $paid, float $total): PaymentScheduleStatus
    {
        if ($paid >= $total - 0.00005) {
            return PaymentScheduleStatus::Paid;
        }

        if ($paid > 0.00005) {
            return PaymentScheduleStatus::Partial;
        }

        return PaymentScheduleStatus::Open;
    }

    private function paymentId(Payment $payment): int
    {
        return is_int($payment->id) ? $payment->id : (int) $payment->id;
    }

    /**
     * @return numeric-string
     */
    private function round4(float $value): string
    {
        return number_format(round($value, 4), 4, '.', '');
    }
}
