<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Banking;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\BankStatementLineStatus;
use Modules\ERP\Models\BankStatement;
use SplFileObject;
use Throwable;

final class BankStatementCsvImporter
{
    /**
     * @param  array{booked_at?:string,value_at?:string,reference?:string,description?:string,amount_doc?:string,currency_doc?:string}  $columns
     */
    public function import(BankStatement $statement, string $path, array $columns = []): int
    {
        $columns = array_merge([
            'booked_at' => 'booked_at',
            'value_at' => 'value_at',
            'reference' => 'reference',
            'description' => 'description',
            'amount_doc' => 'amount_doc',
            'currency_doc' => 'currency_doc',
        ], $columns);

        $rows = $this->readRows($path);
        $created = 0;

        DB::transaction(function () use ($statement, $rows, $columns, &$created): void {
            $statement->loadMissing('bank_account');
            $bank_account = $statement->bank_account;
            $default_currency = $bank_account !== null ? $bank_account->currency : 'EUR';

            foreach ($rows as $index => $row) {
                $this->assertRowIsValid($row, $columns, $index);

                $statement->lines()->create([
                    'company_id' => $statement->company_id,
                    'booked_at' => CarbonImmutable::parse((string) $row[$columns['booked_at']])->toDateString(),
                    'value_at' => isset($row[$columns['value_at']]) && $row[$columns['value_at']] !== ''
                        ? CarbonImmutable::parse((string) $row[$columns['value_at']])->toDateString()
                        : null,
                    'reference' => $row[$columns['reference']] ?? null,
                    'description' => $row[$columns['description']] ?? null,
                    'amount_doc' => $this->normalizeDecimal((string) $row[$columns['amount_doc']]),
                    'currency_doc' => $row[$columns['currency_doc']] ?? $default_currency,
                    'amount_local' => $this->normalizeDecimal((string) $row[$columns['amount_doc']]),
                    'currency_local' => $default_currency,
                    'fx_rate' => '1.00000000',
                    'status' => BankStatementLineStatus::Imported,
                    'raw_payload' => $row,
                ]);
                $created++;
            }

            $statement->imported_at = now();
            $statement->save();
        });

        return $created;
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
