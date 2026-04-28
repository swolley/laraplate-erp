<?php

declare(strict_types=1);

namespace Modules\Business\Services\Accounting;

use Modules\Business\Models\DocumentSequence;

/**
 * Renders a sequence counter using optional {@see DocumentSequence::$format_pattern} tokens.
 */
final class DocumentNumberFormatter
{
    /**
     * Tokens: {prefix}, {suffix}, {number} (zero-padded), {YYYY} (empty when fiscal year is 0).
     */
    public static function format(DocumentSequence $row, int $fiscal_year, int $counter): string
    {
        $padding = max(1, (int) $row->padding);
        $number = str_pad((string) $counter, $padding, '0', STR_PAD_LEFT);
        $year_segment = $fiscal_year > 0 ? (string) $fiscal_year : '';
        $pattern = $row->format_pattern;

        if ($pattern === null || $pattern === '') {
            return self::defaultLayout(
                (string) $row->prefix,
                (string) $row->suffix,
                $fiscal_year,
                $number,
            );
        }

        return str_replace(
            ['{prefix}', '{suffix}', '{number}', '{YYYY}'],
            [(string) $row->prefix, (string) $row->suffix, $number, $year_segment],
            $pattern,
        );
    }

    /**
     * Backward-compatible layout when format_pattern is null (fiscal year digit block before the counter).
     */
    public static function defaultLayout(
        string $prefix,
        string $suffix,
        int $fiscal_year,
        string $padded_number,
    ): string {
        if ($fiscal_year > 0) {
            return $prefix . $fiscal_year . '-' . $padded_number . $suffix;
        }

        return $prefix . $padded_number . $suffix;
    }
}
