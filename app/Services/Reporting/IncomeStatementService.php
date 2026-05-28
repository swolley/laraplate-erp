<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Reporting;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
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
        $rows = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.company_id', $company_id)
            ->whereNotNull('journal_entries.posted_at')
            ->where('journal_entries.posted_at', '>=', $from_date->format('Y-m-d H:i:s'))
            ->where('journal_entries.posted_at', '<=', $to_date->format('Y-m-d H:i:s'))
            ->whereIn('accounts.kind', ['revenue', 'expense'])
            ->groupBy('journal_entry_lines.account_id', 'accounts.code', 'accounts.name', 'accounts.kind')
            ->orderBy('accounts.code')
            ->select([
                'journal_entry_lines.account_id',
                'accounts.code as account_code',
                'accounts.name as account_name',
                'accounts.kind as account_kind',
                DB::raw('SUM(journal_entry_lines.amount_local) as balance'),
            ])
            ->get();

        $revenue = [];
        $expenses = [];
        $total_revenue = 0.0;
        $total_expenses = 0.0;

        foreach ($rows as $row) {
            $balance = (float) $row->balance;

            if ($row->account_kind === 'revenue') {
                $display_balance = abs($balance);
                $revenue[] = [
                    'account_code' => (string) $row->account_code,
                    'account_name' => (string) $row->account_name,
                    'balance' => number_format(round($display_balance, 4), 4, '.', ''),
                ];
                $total_revenue += $display_balance;

                continue;
            }

            $expenses[] = [
                'account_code' => (string) $row->account_code,
                'account_name' => (string) $row->account_name,
                'balance' => number_format(round($balance, 4), 4, '.', ''),
            ];
            $total_expenses += $balance;
        }

        $net_income = $total_revenue - $total_expenses;

        return [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'total_revenue' => number_format(round($total_revenue, 4), 4, '.', ''),
            'total_expenses' => number_format(round($total_expenses, 4), 4, '.', ''),
            'net_income' => number_format(round($net_income, 4), 4, '.', ''),
        ];
    }
}
