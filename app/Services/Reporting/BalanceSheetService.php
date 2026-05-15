<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Reporting;

/**
 * Generates a balance sheet (stato patrimoniale) at a given date.
 */
final readonly class BalanceSheetService
{
    public function __construct(
        private TrialBalanceService $trial_balance_service,
    ) {}

    /**
     * @return array{
     *     assets: array<int, array{account_code: string, account_name: string, balance: string}>,
     *     liabilities: array<int, array{account_code: string, account_name: string, balance: string}>,
     *     equity: array<int, array{account_code: string, account_name: string, balance: string}>,
     *     total_assets: string,
     *     total_liabilities: string,
     *     total_equity: string,
     *     net_income: string,
     *     is_balanced: bool,
     * }
     */
    public function generate(int $company_id, \DateTimeInterface $as_of_date): array
    {
        $trial_balance = $this->trial_balance_service->generate($company_id, $as_of_date);

        $assets = [];
        $liabilities = [];
        $equity = [];
        $total_assets = 0.0;
        $total_liabilities = 0.0;
        $total_equity = 0.0;
        $revenue_balance = 0.0;
        $expense_balance = 0.0;

        foreach ($trial_balance as $row) {
            $balance = (float) $row['balance'];

            match ($row['account_kind']) {
                'asset' => (function () use (&$assets, &$total_assets, $row, $balance): void {
                    $assets[] = [
                        'account_code' => $row['account_code'],
                        'account_name' => $row['account_name'],
                        'balance' => number_format(round($balance, 4), 4, '.', ''),
                    ];
                    $total_assets += $balance;
                })(),
                'liability' => (function () use (&$liabilities, &$total_liabilities, $row, $balance): void {
                    $liabilities[] = [
                        'account_code' => $row['account_code'],
                        'account_name' => $row['account_name'],
                        'balance' => number_format(round(abs($balance), 4), 4, '.', ''),
                    ];
                    $total_liabilities += abs($balance);
                })(),
                'equity' => (function () use (&$equity, &$total_equity, $row, $balance): void {
                    $equity[] = [
                        'account_code' => $row['account_code'],
                        'account_name' => $row['account_name'],
                        'balance' => number_format(round(abs($balance), 4), 4, '.', ''),
                    ];
                    $total_equity += abs($balance);
                })(),
                'revenue' => (function () use (&$revenue_balance, $balance): void {
                    $revenue_balance += $balance;
                })(),
                'expense' => (function () use (&$expense_balance, $balance): void {
                    $expense_balance += $balance;
                })(),
                default => null,
            };
        }

        $net_income = -($revenue_balance + $expense_balance);

        $is_balanced = bccomp(
            number_format(round($total_assets, 4), 4, '.', ''),
            number_format(round($total_liabilities + $total_equity + $net_income, 4), 4, '.', ''),
            4,
        ) === 0;

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => number_format(round($total_assets, 4), 4, '.', ''),
            'total_liabilities' => number_format(round($total_liabilities, 4), 4, '.', ''),
            'total_equity' => number_format(round($total_equity, 4), 4, '.', ''),
            'net_income' => number_format(round($net_income, 4), 4, '.', ''),
            'is_balanced' => $is_balanced,
        ];
    }
}
