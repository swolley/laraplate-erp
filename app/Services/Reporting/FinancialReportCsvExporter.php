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
}
