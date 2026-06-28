<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Reporting;

use RuntimeException;

/**
 * Serializes financial report rows to RFC 4180-style CSV strings.
 */
final class FinancialReportCsvExporter
{
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
        return $this->writeRows([
            ['Account code', 'Account name', 'Account kind', 'Debit', 'Credit', 'Balance'],
            ...array_map(
                static fn (array $row): array => [
                    $row['account_code'],
                    $row['account_name'],
                    $row['account_kind'],
                    $row['debit'],
                    $row['credit'],
                    $row['balance'],
                ],
                $rows,
            ),
        ]);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function writeRows(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open temporary CSV stream.');
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '', "\n");
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        if ($contents === false) {
            throw new RuntimeException('Unable to read temporary CSV stream.');
        }

        return $contents;
    }
}
