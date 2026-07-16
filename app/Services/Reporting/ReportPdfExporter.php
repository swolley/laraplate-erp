<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Reporting;

final class ReportPdfExporter
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function render(string $title, array $rows): string
    {
        $lines = [$title, 'Generated at ' . now()->toISOString(), ''];

        foreach (array_slice($rows, 0, 80) as $row) {
            $lines[] = $this->lineFromRow($row);
        }

        if (count($rows) > 80) {
            $lines[] = sprintf('... %d more rows archived in CSV/payload', count($rows) - 80);
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

    /**
     * @param  list<string>  $lines
     */
    private function pdfFromLines(array $lines): string
    {
        $content = "BT\n/F1 10 Tf\n50 790 Td\n";

        foreach ($lines as $index => $line) {
            $prefix = $index === 0 ? '' : "0 -14 Td\n";
            $content .= $prefix . '(' . $this->escapePdfText(substr($line, 0, 110)) . ") Tj\n";
        }

        $content .= "ET\n";

        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj',
            '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
            '5 0 obj << /Length ' . strlen($content) . " >> stream\n" . $content . 'endstream endobj',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object . "\n";
        }

        $xref_offset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref_offset}\n%%EOF\n";

        return $pdf;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
