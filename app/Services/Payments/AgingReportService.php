<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Payments;

use DateTimeInterface;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\PaymentScheduleLine;

final class AgingReportService
{
    /**
     * @return list<array{party_id: int, party_name: string, current: numeric-string, days_30: numeric-string, days_60: numeric-string, days_90: numeric-string, days_120_plus: numeric-string, total: numeric-string}>
     */
    public function generate(int $company_id, string $direction, ?DateTimeInterface $as_of_date = null): array
    {
        $as_of = $as_of_date instanceof DateTimeInterface ? \Illuminate\Support\Facades\Date::instance($as_of_date) : \Illuminate\Support\Facades\Date::today();

        $invoice_direction = $direction === 'receivable'
            ? InvoiceDirection::Sale
            : InvoiceDirection::Purchase;

        $schedule_table = ERPTables::PaymentScheduleLines->value;
        $invoices_table = ERPTables::Invoices->value;
        $parties_table = ERPTables::Parties->value;

        $schedule_lines = PaymentScheduleLine::query()
            ->where("{$schedule_table}.company_id", $company_id)
            ->whereIn("{$schedule_table}.status", [
                PaymentScheduleStatus::Open->value,
                PaymentScheduleStatus::Partial->value,
            ])
            ->join($invoices_table, "{$invoices_table}.id", '=', "{$schedule_table}.invoice_id")
            ->where("{$invoices_table}.direction", $invoice_direction->value)
            ->join($parties_table, "{$parties_table}.id", '=', "{$invoices_table}.party_id")
            ->select([
                "{$schedule_table}.due_date",
                "{$schedule_table}.amount_doc",
                "{$schedule_table}.paid_amount_doc",
                "{$invoices_table}.party_id",
                "{$parties_table}.name as party_name",
            ])
            ->get();

        /** @var array<int, array{party_id: int, party_name: string, current: numeric-string, days_30: numeric-string, days_60: numeric-string, days_90: numeric-string, days_120_plus: numeric-string, total: numeric-string}> $grouped */
        $grouped = [];

        foreach ($schedule_lines as $line) {
            $party_id = $this->partyIdFromLine($line);
            $remaining = $this->round4((float) $line->amount_doc - (float) $line->paid_amount_doc);

            if ((float) $remaining <= 0.00005) {
                continue;
            }

            $due_date = \Illuminate\Support\Facades\Date::parse($line->due_date);
            $days_overdue = (int) $due_date->diffInDays($as_of, false);
            $bucket = $this->resolveBucket($days_overdue);

            if (! isset($grouped[$party_id])) {
                $grouped[$party_id] = [
                    'party_id' => $party_id,
                    'party_name' => $this->partyNameFromLine($line),
                    'current' => $this->zeroAmount(),
                    'days_30' => $this->zeroAmount(),
                    'days_60' => $this->zeroAmount(),
                    'days_90' => $this->zeroAmount(),
                    'days_120_plus' => $this->zeroAmount(),
                    'total' => $this->zeroAmount(),
                ];
            }

            $party_row = $grouped[$party_id];

            match ($bucket) {
                'current' => $party_row['current'] = $this->add($party_row['current'], $remaining),
                'days_30' => $party_row['days_30'] = $this->add($party_row['days_30'], $remaining),
                'days_60' => $party_row['days_60'] = $this->add($party_row['days_60'], $remaining),
                'days_90' => $party_row['days_90'] = $this->add($party_row['days_90'], $remaining),
                default => $party_row['days_120_plus'] = $this->add($party_row['days_120_plus'], $remaining),
            };

            $party_row['total'] = $this->add($party_row['total'], $remaining);
            $grouped[$party_id] = $party_row;
        }

        return array_values($grouped);
    }

    private function partyIdFromLine(PaymentScheduleLine $line): int
    {
        $party_id = $line->getAttribute('party_id');

        if (is_int($party_id)) {
            return $party_id;
        }

        if (is_string($party_id) && is_numeric($party_id)) {
            return (int) $party_id;
        }

        if (is_float($party_id)) {
            return (int) $party_id;
        }

        return 0;
    }

    private function partyNameFromLine(PaymentScheduleLine $line): string
    {
        $party_name = $line->getAttribute('party_name');

        return is_string($party_name) ? $party_name : '';
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

    /**
     * @return numeric-string
     */
    private function round4(float $value): string
    {
        return number_format(round($value, 4), 4, '.', '');
    }

    /**
     * @return numeric-string
     */
    private function zeroAmount(): string
    {
        return '0.0000';
    }

    /**
     * @param  numeric-string  $a
     * @param  numeric-string  $b
     * @return numeric-string
     */
    private function add(string $a, string $b): string
    {
        return $this->round4((float) $a + (float) $b);
    }
}
