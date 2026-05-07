<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Reporting;

use Illuminate\Support\Facades\DB;
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
    public function generate(int $company_id, \DateTimeInterface $as_of_date): array
    {
        $rows = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.company_id', $company_id)
            ->whereNotNull('journal_entries.posted_at')
            ->where('journal_entries.posted_at', '<=', $as_of_date->format('Y-m-d H:i:s'))
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
