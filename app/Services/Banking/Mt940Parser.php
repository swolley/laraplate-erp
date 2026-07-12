<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Banking;

use Illuminate\Validation\ValidationException;
use Modules\ERP\Contracts\BankStatementParser;
use Override;

final class Mt940Parser implements BankStatementParser
{
    #[Override]
    public function supports(string $path, string $contents): bool
    {
        return str_ends_with(mb_strtolower($path), '.sta')
            || str_contains($contents, ':61:')
            || str_contains($contents, ':86:');
    }

    /**
     * @return list<BankStatementLineData>
     */
    #[Override]
    public function parse(string $contents): array
    {
        $currency = $this->currency($contents);
        $records = preg_split('/(?=^:61:)/m', $contents) ?: [];
        $lines = [];

        foreach ($records as $record) {
            if (! str_starts_with($record, ':61:')) {
                continue;
            }

            $statement_line = strtok($record, "\r\n");

            if (! is_string($statement_line)) {
                continue;
            }

            $description = $this->description($record);
            $parsed = $this->parseStatementLine($statement_line);

            $lines[] = new BankStatementLineData(
                booked_at: $parsed['date'],
                value_at: $parsed['date'],
                reference: $parsed['reference'],
                description: $description,
                amount_doc: $parsed['amount'],
                currency_doc: $currency,
                raw_payload: [
                    'format' => 'mt940',
                    'statement_line' => $statement_line,
                ],
            );
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'file' => ['The MT940 file does not contain supported :61: transaction lines.'],
            ]);
        }

        return $lines;
    }

    /**
     * @return array{date: string, reference: string|null, amount: numeric-string}
     */
    private function parseStatementLine(string $statement_line): array
    {
        $line = mb_trim(mb_substr($statement_line, 4));

        if (! preg_match('/^(?<date>\d{6})(?<entry_date>\d{4})?(?<direction>[CD])(?<amount>\d+(?:,\d+)?)(?<rest>.*)$/', $line, $matches)) {
            throw ValidationException::withMessages([
                'file' => ['The MT940 file contains an unsupported :61: transaction line.'],
            ]);
        }

        $reference = null;
        $rest = (string) $matches['rest'];

        if (preg_match('/\/\/(?<reference>[^\r\n]+)/', $rest, $reference_matches)) {
            $reference = mb_trim($reference_matches['reference']);
        }

        $amount = (float) str_replace(',', '.', (string) $matches['amount']);

        if ($matches['direction'] === 'D') {
            $amount *= -1;
        }

        return [
            'date' => $this->date((string) $matches['date']),
            'reference' => $reference === '' ? null : $reference,
            'amount' => number_format(round($amount, 4), 4, '.', ''),
        ];
    }

    private function description(string $record): string
    {
        if (preg_match('/^:86:(?<description>.*?)(?=^:\d{2}[A-Z]?:|\z)/ms', $record, $matches)) {
            $description = preg_replace('/\s+/', ' ', mb_trim((string) $matches['description']));

            if (is_string($description) && $description !== '') {
                return $description;
            }
        }

        return 'MT940 transaction';
    }

    private function currency(string $contents): string
    {
        if (preg_match('/^:6[02][FM]:[CD]\d{6}(?<currency>[A-Z]{3})/m', $contents, $matches)) {
            return (string) $matches['currency'];
        }

        return 'EUR';
    }

    private function date(string $yymmdd): string
    {
        $year = (int) mb_substr($yymmdd, 0, 2);
        $century = $year >= 70 ? 1900 : 2000;

        return sprintf(
            '%04d-%02d-%02d',
            $century + $year,
            (int) mb_substr($yymmdd, 2, 2),
            (int) mb_substr($yymmdd, 4, 2),
        );
    }
}
