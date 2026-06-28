<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Reporting;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\JournalEntryLine;

/**
 * Generates an income statement (conto economico) for a date range.
 */
final class IncomeStatementService
{
    /**
     * @return array{
     *     revenue: array<int, array{account_code: string, account_name: string, balance: string}>,
     *     expenses: array<int, array{account_code: string, account_name: string, balance: string}>,
     *     total_revenue: string,
     *     total_expenses: string,
     *     net_income: string,
     * }
     */
    public function generate(int $company_id, DateTimeInterface $from_date, DateTimeInterface $to_date): array
    {
        $accounts_table = ERPTables::Accounts->value;
        $journal_entries_table = ERPTables::JournalEntries->value;
        $journal_entry_lines_table = ERPTables::JournalEntryLines->value;

        $rows = JournalEntryLine::query()
            ->join($journal_entries_table, "{$journal_entries_table}.id", '=', "{$journal_entry_lines_table}.journal_entry_id")
            ->join($accounts_table, "{$accounts_table}.id", '=', "{$journal_entry_lines_table}.account_id")
            ->where("{$journal_entries_table}.company_id", $company_id)
            ->whereNotNull("{$journal_entries_table}.posted_at")
            ->where("{$journal_entries_table}.posted_at", '>=', $from_date->format('Y-m-d H:i:s'))
            ->where("{$journal_entries_table}.posted_at", '<=', $to_date->format('Y-m-d H:i:s'))
            ->whereIn("{$accounts_table}.kind", ['revenue', 'expense'])
            ->groupBy("{$journal_entry_lines_table}.account_id", "{$accounts_table}.code", "{$accounts_table}.name", "{$accounts_table}.kind")
            ->orderBy("{$accounts_table}.code")
            ->select([
                "{$journal_entry_lines_table}.account_id",
                "{$accounts_table}.code as account_code",
                "{$accounts_table}.name as account_name",
                "{$accounts_table}.kind as account_kind",
                DB::raw("SUM({$journal_entry_lines_table}.amount_local) as balance"),
            ])
            ->get();

        $revenue = [];
        $expenses = [];
        $total_revenue = 0.0;
        $total_expenses = 0.0;

        foreach ($rows as $row) {
            $aggregate = $this->aggregateRow($row);
            $balance = $aggregate['balance'];

            if ($aggregate['account_kind'] === 'revenue') {
                $display_balance = abs($balance);
                $revenue[] = [
                    'account_code' => $aggregate['account_code'],
                    'account_name' => $aggregate['account_name'],
                    'balance' => $this->formatAmount($display_balance),
                ];
                $total_revenue += $display_balance;

                continue;
            }

            $expenses[] = [
                'account_code' => $aggregate['account_code'],
                'account_name' => $aggregate['account_name'],
                'balance' => $this->formatAmount($balance),
            ];
            $total_expenses += $balance;
        }

        $net_income = $total_revenue - $total_expenses;

        return [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'total_revenue' => $this->formatAmount($total_revenue),
            'total_expenses' => $this->formatAmount($total_expenses),
            'net_income' => $this->formatAmount($net_income),
        ];
    }

    /**
     * @return array{account_code: string, account_name: string, account_kind: string, balance: float}
     */
    private function aggregateRow(JournalEntryLine $row): array
    {
        $balance = $row->getAttribute('balance');
        $account_code = $row->getAttribute('account_code');
        $account_name = $row->getAttribute('account_name');
        $account_kind = $row->getAttribute('account_kind');

        return [
            'account_code' => is_string($account_code) ? $account_code : '',
            'account_name' => is_string($account_name) ? $account_name : '',
            'account_kind' => is_string($account_kind) ? $account_kind : '',
            'balance' => is_numeric($balance) ? (float) $balance : 0.0,
        ];
    }

    /**
     * @return numeric-string
     */
    private function formatAmount(float $amount): string
    {
        return number_format(round($amount, 4), 4, '.', '');
    }
}
