<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Banking;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Models\BankStatement;
use SplFileObject;
use Throwable;

final readonly class BankStatementCsvImporter
{
    public function __construct(
        private BankStatementImportService $import_service,
    ) {}

    /**
     * @param  array{booked_at?:string,value_at?:string,reference?:string,description?:string,amount_doc?:string,currency_doc?:string}  $columns
     */
    public function import(BankStatement $statement, string $path, array $columns = []): int
    {
        return $this->import_service->importLines($statement, $this->parse($path, $columns));
    }

    /**
     * @param  array{booked_at?:string,value_at?:string,reference?:string,description?:string,amount_doc?:string,currency_doc?:string}  $columns
     * @return list<BankStatementLineData>
     */
    public function parse(string $path, array $columns = []): array
    {
        $columns = array_merge([
            'booked_at' => 'booked_at',
            'value_at' => 'value_at',
            'reference' => 'reference',
            'description' => 'description',
            'amount_doc' => 'amount_doc',
            'currency_doc' => 'currency_doc',
        ], $columns);

        return $this->parseRows($path, $columns);
    }

    /**
     * @param  array<string, string>  $columns
     * @return list<BankStatementLineData>
     */
    private function parseRows(string $path, array $columns): array
    {
        $rows = $this->readRows($path);
        $lines = [];

        foreach ($rows as $index => $row) {
            $this->assertRowIsValid($row, $columns, $index);

            $lines[] = new BankStatementLineData(
                booked_at: CarbonImmutable::parse((string) $row[$columns['booked_at']])->toDateString(),
                value_at: isset($row[$columns['value_at']]) && $row[$columns['value_at']] !== ''
                    ? CarbonImmutable::parse((string) $row[$columns['value_at']])->toDateString()
                    : null,
                reference: $row[$columns['reference']] ?? null,
                description: (string) ($row[$columns['description']] ?? ''),
                amount_doc: $this->decimal4((string) $row[$columns['amount_doc']]),
                currency_doc: (string) ($row[$columns['currency_doc']] ?? ''),
                raw_payload: array_merge(['format' => 'csv'], $row),
            );
        }

        return $lines;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, string>  $columns
     */
    private function assertRowIsValid(array $row, array $columns, int $index): void
    {
        $booked_at = mb_trim((string) ($row[$columns['booked_at']] ?? ''));
        $value_at = mb_trim((string) ($row[$columns['value_at']] ?? ''));
        $amount = mb_trim((string) ($row[$columns['amount_doc']] ?? ''));
        $description = mb_trim((string) ($row[$columns['description']] ?? ''));

        $errors = [];
        $invalid_dates = [];

        if ($booked_at === '') {
            $errors[] = 'a booked date';
        } elseif (! $this->isParseableDate($booked_at)) {
            $invalid_dates[] = 'booked date';
        }

        if ($value_at !== '' && ! $this->isParseableDate($value_at)) {
            $invalid_dates[] = 'value date';
        }

        if ($amount === '' || ! is_numeric($this->normalizeDecimal($amount))) {
            $errors[] = 'a numeric amount';
        }

        if ($description === '') {
            $errors[] = 'a description';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'file' => ['Row ' . ($index + 1) . ' is missing ' . implode(', ', $errors) . '.'],
            ]);
        }

        if ($invalid_dates !== []) {
            throw ValidationException::withMessages([
                'file' => ['Row ' . ($index + 1) . ' contains an invalid ' . implode(', ', $invalid_dates) . '.'],
            ]);
        }
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function readRows(string $path): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(',');

        $headers = null;
        $rows = [];

        foreach ($file as $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            if ($headers === null) {
                $headers = [];

                foreach ($row as $header) {
                    if (! is_scalar($header)) {
                        continue;
                    }

                    $headers[] = mb_trim((string) $header);
                }

                if ($headers === []) {
                    continue;
                }

                if (count($headers) !== count(array_unique($headers))) {
                    throw ValidationException::withMessages([
                        'file' => ['The CSV header contains duplicate column names.'],
                    ]);
                }

                continue;
            }

            $rows[] = array_combine($headers, array_pad($row, count($headers), null));
        }

        return $rows;
    }

    private function normalizeDecimal(string $value): string
    {
        return str_replace(',', '.', mb_trim($value));
    }

    /**
     * @return numeric-string
     */
    private function decimal4(string $value): string
    {
        return number_format(round((float) $this->normalizeDecimal($value), 4), 4, '.', '');
    }

    private function isParseableDate(string $value): bool
    {
        try {
            CarbonImmutable::parse($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
