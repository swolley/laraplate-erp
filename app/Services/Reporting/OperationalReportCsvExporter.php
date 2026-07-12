<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Reporting;

use Modules\Core\Services\Export\TabularCsvExporter;

final readonly class OperationalReportCsvExporter
{
    public function __construct(
        private TabularCsvExporter $csv_exporter,
    ) {}

    /**
     * @param  array{
     *     by_status: array<string, array{status: string, count: int, expected_value_doc: string, expected_value_local: string}>,
     *     total_count: int,
     *     won_count: int,
     *     lost_count: int,
     *     won_value_doc: string,
     *     won_value_local: string,
     *     total_expected_value_doc: string,
     *     total_expected_value_local: string,
     * }  $report
     */
    public function salesPipeline(array $report): string
    {
        return $this->csv_exporter->export(
            columns: [
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'count', 'label' => 'Count'],
                ['key' => 'expected_value_doc', 'label' => 'Expected doc'],
                ['key' => 'expected_value_local', 'label' => 'Expected local'],
            ],
            rows: array_values($report['by_status']),
        );
    }

    /**
     * @param  array{
     *     rows: array<int, array{
     *         sku: string,
     *         item_name: string,
     *         warehouse_code: string,
     *         warehouse_name: string,
     *         quantity: string,
     *         weighted_avg_cost: string,
     *         value: string,
     *     }>,
     *     total_quantity: string,
     *     total_value: string,
     * }  $report
     */
    public function stockValuation(array $report): string
    {
        return $this->csv_exporter->export(
            columns: [
                ['key' => 'sku', 'label' => 'SKU'],
                ['key' => 'item_name', 'label' => 'Item name'],
                ['key' => 'warehouse_code', 'label' => 'Warehouse code'],
                ['key' => 'warehouse_name', 'label' => 'Warehouse name'],
                ['key' => 'quantity', 'label' => 'Quantity'],
                ['key' => 'weighted_avg_cost', 'label' => 'Weighted avg cost'],
                ['key' => 'value', 'label' => 'Value'],
            ],
            rows: $report['rows'],
        );
    }
}
