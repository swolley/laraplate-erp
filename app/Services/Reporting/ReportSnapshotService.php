<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Reporting;

use JsonException;
use Modules\ERP\Models\ReportSnapshot;

final readonly class ReportSnapshotService
{
    public function __construct(
        private ReportPdfExporter $pdf_exporter,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $payload
     */
    public function archive(int $company_id, string $report_key, string $title, array $parameters, array $payload, string $csv_content): ReportSnapshot
    {
        $rows = $this->rowsForPdf($payload);
        $pdf_content = $this->pdf_exporter->render($title, $rows);
        $hash = $this->hash($company_id, $report_key, $parameters, $payload, $csv_content, $pdf_content);

        return ReportSnapshot::query()->create([
            'company_id' => $company_id,
            'report_key' => $report_key,
            'title' => $title,
            'parameters' => $parameters,
            'snapshot_payload' => $payload,
            'csv_content' => $csv_content,
            'pdf_content' => base64_encode($pdf_content),
            'content_hash' => $hash,
            'generated_at' => now(),
            'is_immutable' => true,
        ]);
    }

    public function decodedPdf(ReportSnapshot $snapshot): string
    {
        return base64_decode((string) $snapshot->pdf_content, true) ?: '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function rowsForPdf(array $payload): array
    {
        if ($payload === []) {
            return [['message' => 'No rows']];
        }

        if (array_is_list($payload)) {
            return array_map(static fn (mixed $row): array => is_array($row) ? $row : ['value' => $row], $payload);
        }

        $rows = [];

        foreach ($payload as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                foreach ($value as $row) {
                    $rows[] = is_array($row) ? array_merge(['section' => $key], $row) : ['section' => $key, 'value' => $row];
                }

                continue;
            }

            $rows[] = ['metric' => $key, 'value' => is_scalar($value) || $value === null ? $value : json_encode($value, JSON_THROW_ON_ERROR)];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    private function hash(int $company_id, string $report_key, array $parameters, array $payload, string $csv_content, string $pdf_content): string
    {
        return hash('sha256', json_encode([
            'company_id' => $company_id,
            'report_key' => $report_key,
            'parameters' => $parameters,
            'payload' => $payload,
            'csv' => $csv_content,
            'pdf' => $pdf_content,
        ], JSON_THROW_ON_ERROR));
    }
}
