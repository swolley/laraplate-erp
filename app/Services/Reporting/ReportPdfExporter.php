<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Reporting;

use Modules\Core\Services\Export\TabularPdfExporter;

/**
 * Renders a report payload as a simple PDF by dumping each row's values on a
 * single line. Reuses Core's {@see TabularPdfExporter} PDF plumbing and only
 * customizes the row layout (free-form value dump, no explicit column spec).
 */
final class ReportPdfExporter extends TabularPdfExporter
{
    private const int MAX_ROWS = 80;

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function render(string $title, array $rows): string
    {
        $lines = [$title, 'Generated at ' . now()->toISOString(), ''];

        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $lines[] = $this->lineFromRow($row);
        }

        if (count($rows) > self::MAX_ROWS) {
            $lines[] = sprintf('... %d more rows archived in CSV/payload', count($rows) - self::MAX_ROWS);
        }

        return $this->pdfFromLines($lines);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function lineFromRow(array $row): string
    {
        return implode(' | ', array_map(
            static fn (mixed $value): string => is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR),
            $row,
        ));
    }
}
