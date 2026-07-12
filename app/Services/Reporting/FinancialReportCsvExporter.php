<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Reporting;

use Modules\Core\Services\Export\TabularCsvExporter;

/**
 * Defines ERP financial report CSV layouts.
 */
final readonly class FinancialReportCsvExporter
{
    public function __construct(
        private TabularCsvExporter $csv_exporter,
    ) {}

    /**
     * @param  array<int, array{
     *     account_code: string,
     *     account_name: string,
     *     account_kind: string,
     *     debit: string,
     *     credit: string,
     *     balance: string,
     * }>  $rows
     */
    public function trialBalance(array $rows): string
    {
        return $this->csv_exporter->export(
            columns: [
                ['key' => 'account_code', 'label' => 'Account code'],
                ['key' => 'account_name', 'label' => 'Account name'],
                ['key' => 'account_kind', 'label' => 'Account kind'],
                ['key' => 'debit', 'label' => 'Debit'],
                ['key' => 'credit', 'label' => 'Credit'],
                ['key' => 'balance', 'label' => 'Balance'],
            ],
            rows: $rows,
        );
    }

    /**
     * @param  array{
     *     revenue: array<int, array{account_code: string, account_name: string, balance: string}>,
     *     expenses: array<int, array{account_code: string, account_name: string, balance: string}>,
     *     total_revenue: string,
     *     total_expenses: string,
     *     net_income: string,
     * }  $report
     */
    public function incomeStatement(array $report): string
    {
        $rows = [];

        foreach ($report['revenue'] as $row) {
            $rows[] = [
                'section' => 'Revenue',
                'account_code' => $row['account_code'],
                'account_name' => $row['account_name'],
                'balance' => $row['balance'],
            ];
        }

        foreach ($report['expenses'] as $row) {
            $rows[] = [
                'section' => 'Expense',
                'account_code' => $row['account_code'],
                'account_name' => $row['account_name'],
                'balance' => $row['balance'],
            ];
        }

        $rows[] = [
            'section' => 'Total',
            'account_code' => 'Total revenue',
            'account_name' => '',
            'balance' => $report['total_revenue'],
        ];
        $rows[] = [
            'section' => 'Total',
            'account_code' => 'Total expenses',
            'account_name' => '',
            'balance' => $report['total_expenses'],
        ];
        $rows[] = [
            'section' => 'Total',
            'account_code' => 'Net income',
            'account_name' => '',
            'balance' => $report['net_income'],
        ];

        return $this->csv_exporter->export(
            columns: [
                ['key' => 'section', 'label' => 'Section'],
                ['key' => 'account_code', 'label' => 'Account code'],
                ['key' => 'account_name', 'label' => 'Account name'],
                ['key' => 'balance', 'label' => 'Balance'],
            ],
            rows: $rows,
        );
    }

    /**
     * @param  array{
     *     assets: array<int, array{account_code: string, account_name: string, balance: string}>,
     *     liabilities: array<int, array{account_code: string, account_name: string, balance: string}>,
     *     equity: array<int, array{account_code: string, account_name: string, balance: string}>,
     *     total_assets: string,
     *     total_liabilities: string,
     *     total_equity: string,
     *     net_income: string,
     *     is_balanced: bool,
     * }  $report
     */
    public function balanceSheet(array $report): string
    {
        $rows = [];

        foreach ($report['assets'] as $row) {
            $rows[] = $this->statementRow('Assets', $row);
        }

        foreach ($report['liabilities'] as $row) {
            $rows[] = $this->statementRow('Liabilities', $row);
        }

        foreach ($report['equity'] as $row) {
            $rows[] = $this->statementRow('Equity', $row);
        }

        $rows[] = $this->totalRow('Total assets', $report['total_assets']);
        $rows[] = $this->totalRow('Total liabilities', $report['total_liabilities']);
        $rows[] = $this->totalRow('Total equity', $report['total_equity']);
        $rows[] = $this->totalRow('Net income', $report['net_income']);
        $rows[] = $this->totalRow('Balanced', $report['is_balanced'] ? 'Yes' : 'No');

        return $this->csv_exporter->export(
            columns: [
                ['key' => 'section', 'label' => 'Section'],
                ['key' => 'account_code', 'label' => 'Account code'],
                ['key' => 'account_name', 'label' => 'Account name'],
                ['key' => 'balance', 'label' => 'Balance'],
            ],
            rows: $rows,
        );
    }

    /**
     * @param  array{account_code: string, account_name: string, balance: string}  $row
     * @return array{section: string, account_code: string, account_name: string, balance: string}
     */
    private function statementRow(string $section, array $row): array
    {
        return [
            'section' => $section,
            'account_code' => $row['account_code'],
            'account_name' => $row['account_name'],
            'balance' => $row['balance'],
        ];
    }

    /**
     * @return array{section: string, account_code: string, account_name: string, balance: string}
     */
    private function totalRow(string $label, string $value): array
    {
        return [
            'section' => 'Total',
            'account_code' => $label,
            'account_name' => '',
            'balance' => $value,
        ];
    }
}
