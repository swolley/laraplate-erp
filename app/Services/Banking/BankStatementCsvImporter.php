<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Banking;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\BankStatementLineStatus;
use Modules\ERP\Models\BankStatement;
use SplFileObject;

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
            foreach ($rows as $row) {
                $statement->lines()->create([
                    'company_id' => $statement->company_id,
                    'booked_at' => CarbonImmutable::parse((string) $row[$columns['booked_at']])->toDateString(),
                    'value_at' => isset($row[$columns['value_at']]) && $row[$columns['value_at']] !== ''
                        ? CarbonImmutable::parse((string) $row[$columns['value_at']])->toDateString()
                        : null,
                    'reference' => $row[$columns['reference']] ?? null,
                    'description' => $row[$columns['description']] ?? null,
                    'amount_doc' => $this->normalizeDecimal((string) $row[$columns['amount_doc']]),
                    'currency_doc' => $row[$columns['currency_doc']] ?? $statement->bank_account->currency ?? 'EUR',
                    'amount_local' => $this->normalizeDecimal((string) $row[$columns['amount_doc']]),
                    'currency_local' => $statement->bank_account->currency ?? 'EUR',
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
                $headers = array_map(static fn (string|array|false $header): string => mb_trim((string) $header), $row);

                if (count($headers) !== count(array_unique($headers))) {
                    throw ValidationException::withMessages([
                        'file' => ['The CSV header contains duplicate column names.'],
                    ]);
                }

                continue;
            }

            $combined = array_combine($headers, array_pad($row, count($headers), null));

            $rows[] = $combined;
        }

        return $rows;
    }

    private function normalizeDecimal(string $value): string
    {
        return str_replace(',', '.', mb_trim($value));
    }
}
