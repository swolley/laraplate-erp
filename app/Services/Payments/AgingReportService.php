<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Payments;

use Carbon\Carbon;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Models\PaymentScheduleLine;

final class AgingReportService
{
    /**
     * @return array<int, array{party_id: int, party_name: string, current: string, days_30: string, days_60: string, days_90: string, days_120_plus: string, total: string}>
     */
    public function generate(int $company_id, string $direction, ?\DateTimeInterface $as_of_date = null): array
    {
        $as_of = $as_of_date !== null ? Carbon::instance($as_of_date) : Carbon::today();

        $invoice_direction = $direction === 'receivable'
            ? InvoiceDirection::Sale
            : InvoiceDirection::Purchase;

        $schedule_lines = PaymentScheduleLine::query()
            ->where('payment_schedule_lines.company_id', $company_id)
            ->whereIn('payment_schedule_lines.status', [
                PaymentScheduleStatus::Open->value,
                PaymentScheduleStatus::Partial->value,
            ])
            ->join('invoices', 'invoices.id', '=', 'payment_schedule_lines.invoice_id')
            ->where('invoices.direction', $invoice_direction->value)
            ->join('parties', 'parties.id', '=', 'invoices.party_id')
            ->select([
                'payment_schedule_lines.due_date',
                'payment_schedule_lines.amount_doc',
                'payment_schedule_lines.paid_amount_doc',
                'invoices.party_id',
                'parties.name as party_name',
            ])
            ->get();

        $grouped = [];

        foreach ($schedule_lines as $line) {
            $party_id = (int) $line->party_id;
            $remaining = $this->round4((float) $line->amount_doc - (float) $line->paid_amount_doc);

            if ((float) $remaining <= 0.00005) {
                continue;
            }

            $due_date = Carbon::parse($line->due_date);
            $days_overdue = (int) $due_date->diffInDays($as_of, false);
            $bucket = $this->resolveBucket($days_overdue);

            if (! isset($grouped[$party_id])) {
                $grouped[$party_id] = [
                    'party_id' => $party_id,
                    'party_name' => (string) $line->party_name,
                    'current' => '0.0000',
                    'days_30' => '0.0000',
                    'days_60' => '0.0000',
                    'days_90' => '0.0000',
                    'days_120_plus' => '0.0000',
                    'total' => '0.0000',
                ];
            }

            $grouped[$party_id][$bucket] = $this->add($grouped[$party_id][$bucket], $remaining);
            $grouped[$party_id]['total'] = $this->add($grouped[$party_id]['total'], $remaining);
        }

        return array_values($grouped);
    }

    private function resolveBucket(int $days_overdue): string
    {
        if ($days_overdue <= 0) {
            return 'current';
        }

        if ($days_overdue <= 30) {
            return 'days_30';
        }

        if ($days_overdue <= 60) {
            return 'days_60';
        }

        if ($days_overdue <= 90) {
            return 'days_90';
        }

        return 'days_120_plus';
    }

    private function round4(float $value): string
    {
        return number_format(round($value, 4), 4, '.', '');
    }

    private function add(string $a, string $b): string
    {
        return $this->round4((float) $a + (float) $b);
    }
}
