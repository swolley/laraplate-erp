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
            $aggregate = $this->aggregateRow($row);
            $balance = $aggregate['balance'];
            $debit = $balance > 0 ? $balance : 0.0;
            $credit = $balance < 0 ? abs($balance) : 0.0;

            $result[] = [
                'account_id' => $aggregate['account_id'],
                'account_code' => $aggregate['account_code'],
                'account_name' => $aggregate['account_name'],
                'account_kind' => $aggregate['account_kind'],
                'debit' => $this->formatAmount($debit),
                'credit' => $this->formatAmount($credit),
                'balance' => $this->formatAmount($balance),
            ];
        }

        return $result;
    }

    /**
     * @return array{account_id: int, account_code: string, account_name: string, account_kind: string, balance: float}
     */
    private function aggregateRow(JournalEntryLine $row): array
    {
        $account_id = $row->getAttribute('account_id');
        $balance = $row->getAttribute('balance');
        $account_code = $row->getAttribute('account_code');
        $account_name = $row->getAttribute('account_name');
        $account_kind = $row->getAttribute('account_kind');

        return [
            'account_id' => $this->accountIdFromAggregate($account_id),
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

    private function accountIdFromAggregate(mixed $account_id): int
    {
        if (is_int($account_id)) {
            return $account_id;
        }

        if (is_string($account_id) && is_numeric($account_id)) {
            return (int) $account_id;
        }

        return 0;
    }
}
