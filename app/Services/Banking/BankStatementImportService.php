<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Banking;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\BankStatementLineStatus;
use Modules\ERP\Contracts\BankStatementParser;
use Modules\ERP\Models\BankStatement;

final readonly class BankStatementImportService
{
    /**
     * @var list<BankStatementParser>
     */
    private array $parsers;

    public function __construct(
        Camt053Parser $camt053_parser,
        Mt940Parser $mt940_parser,
    ) {
        $this->parsers = [$camt053_parser, $mt940_parser];
    }

    public function importFile(BankStatement $statement, string $path, string $format = 'auto'): int
    {
        return $this->importLines($statement, $this->parseFile($path, $format));
    }

    /**
     * @return list<BankStatementLineData>
     */
    public function parseFile(string $path, string $format = 'auto'): array
    {
        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw ValidationException::withMessages([
                'file' => ['The bank statement file could not be read.'],
            ]);
        }

        $parser = $this->parserFor($path, $contents, $format);

        return $parser->parse($contents);
    }

    /**
     * @param  list<BankStatementLineData>  $lines
     */
    public function importLines(BankStatement $statement, array $lines): int
    {
        $created = 0;

        DB::transaction(function () use ($statement, $lines, &$created): void {
            $statement->loadMissing('bank_account');
            $default_currency = $statement->bank_account?->currency ?? 'EUR';

            foreach ($lines as $index => $line) {
                $this->assertLineIsValid($line, $index);

                $currency = $line->currency_doc !== '' ? $line->currency_doc : $default_currency;

                $statement->lines()->create([
                    'company_id' => $statement->company_id,
                    'booked_at' => CarbonImmutable::parse($line->booked_at)->toDateString(),
                    'value_at' => $line->value_at !== null ? CarbonImmutable::parse($line->value_at)->toDateString() : null,
                    'reference' => $line->reference,
                    'description' => $line->description,
                    'amount_doc' => $line->amount_doc,
                    'currency_doc' => $currency,
                    'amount_local' => $line->amount_doc,
                    'currency_local' => $default_currency,
                    'fx_rate' => '1.00000000',
                    'status' => BankStatementLineStatus::Imported,
                    'raw_payload' => $line->raw_payload,
                ]);
                $created++;
            }

            $statement->imported_at = now();
            $statement->save();
        });

        return $created;
    }

    private function parserFor(string $path, string $contents, string $format): BankStatementParser
    {
        $format = mb_strtolower($format);

        foreach ($this->parsers as $parser) {
            if ($format === 'camt053' && $parser instanceof Camt053Parser) {
                return $parser;
            }

            if ($format === 'mt940' && $parser instanceof Mt940Parser) {
                return $parser;
            }

            if ($format === 'auto' && $parser->supports($path, $contents)) {
                return $parser;
            }
        }

        throw ValidationException::withMessages([
            'file' => ['The bank statement file format is not supported.'],
        ]);
    }

    private function assertLineIsValid(BankStatementLineData $line, int $index): void
    {
        if ($line->booked_at === '' || $line->description === '' || ! is_numeric($line->amount_doc)) {
            throw ValidationException::withMessages([
                'file' => ['Bank statement line ' . ($index + 1) . ' is missing a booked date, description, or numeric amount.'],
            ]);
        }
    }
}
