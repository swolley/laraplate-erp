<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Reporting;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\JournalEntryLine;

/**
 * Generates a trial balance (bilancio di verifica) at a given date.
 */
final class TrialBalanceService
{
    /**
     * @return array<int, array{
     *     account_id: int,
     *     account_code: string,
     *     account_name: string,
     *     account_kind: string,
     *     debit: string,
     *     credit: string,
     *     balance: string,
     * }>
     */
    public function generate(int $company_id, DateTimeInterface $as_of_date): array
    {
        $accounts_table = ERPTables::Accounts->value;
        $journal_entries_table = ERPTables::JournalEntries->value;
        $journal_entry_lines_table = ERPTables::JournalEntryLines->value;

        $rows = JournalEntryLine::query()
            ->join($journal_entries_table, "{$journal_entries_table}.id", '=', "{$journal_entry_lines_table}.journal_entry_id")
            ->join($accounts_table, "{$accounts_table}.id", '=', "{$journal_entry_lines_table}.account_id")
            ->where("{$journal_entries_table}.company_id", $company_id)
            ->whereNotNull("{$journal_entries_table}.posted_at")
            ->where("{$journal_entries_table}.posted_at", '<=', $as_of_date->format('Y-m-d H:i:s'))
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

        $result = [];

        foreach ($rows as $row) {
            $balance = (float) $row->balance;
            $debit = $balance > 0 ? $balance : 0.0;
            $credit = $balance < 0 ? abs($balance) : 0.0;

            $result[] = [
                'account_id' => (int) $row->account_id,
                'account_code' => (string) $row->account_code,
                'account_name' => (string) $row->account_name,
                'account_kind' => (string) $row->account_kind,
                'debit' => number_format(round($debit, 4), 4, '.', ''),
                'credit' => number_format(round($credit, 4), 4, '.', ''),
                'balance' => number_format(round($balance, 4), 4, '.', ''),
            ];
        }

        return $result;
    }
}
