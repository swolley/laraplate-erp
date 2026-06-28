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
}
