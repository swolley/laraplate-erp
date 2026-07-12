<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Banking;

use Illuminate\Validation\ValidationException;
use Modules\ERP\Contracts\BankStatementParser;
use Override;
use SimpleXMLElement;

final class Camt053Parser implements BankStatementParser
{
    #[Override]
    public function supports(string $path, string $contents): bool
    {
        return str_contains(mb_strtolower($path), 'camt')
            || str_contains($contents, '<BkToCstmrStmt')
            || str_contains($contents, ':camt.053.');
    }

    /**
     * @return list<BankStatementLineData>
     */
    #[Override]
    public function parse(string $contents): array
    {
        $xml = @simplexml_load_string($contents);

        if (! $xml instanceof SimpleXMLElement) {
            throw ValidationException::withMessages([
                'file' => ['The CAMT.053 file is not valid XML.'],
            ]);
        }

        /** @var list<SimpleXMLElement> $entries */
        $entries = $xml->xpath('//*[local-name()="Ntry"]') ?: [];
        $lines = [];

        foreach ($entries as $index => $entry) {
            $amount = $this->text($entry, './*[local-name()="Amt"]');
            $currency = $this->attribute($entry, './*[local-name()="Amt"]', 'Ccy') ?? 'EUR';
            $direction = mb_strtoupper($this->text($entry, './*[local-name()="CdtDbtInd"]'));
            $booked_at = $this->text($entry, './*[local-name()="BookgDt"]/*[local-name()="Dt"]');
            $value_at = $this->nullableText($entry, './*[local-name()="ValDt"]/*[local-name()="Dt"]');
            $reference = $this->nullableText($entry, './/*[local-name()="EndToEndId"]')
                ?? $this->nullableText($entry, './*[local-name()="NtryRef"]');
            $description = $this->nullableText($entry, './/*[local-name()="Ustrd"]')
                ?? $this->nullableText($entry, './*[local-name()="AddtlNtryInf"]');

            if ($booked_at === '' || $amount === '' || $description === null) {
                throw ValidationException::withMessages([
                    'file' => ['CAMT.053 entry ' . ($index + 1) . ' is missing a booked date, amount, or description.'],
                ]);
            }

            $lines[] = new BankStatementLineData(
                booked_at: $booked_at,
                value_at: $value_at,
                reference: $reference,
                description: $description,
                amount_doc: $this->signedAmount($amount, $direction),
                currency_doc: $currency,
                raw_payload: [
                    'format' => 'camt053',
                    'entry_index' => $index,
                    'entry_reference' => $this->nullableText($entry, './*[local-name()="NtryRef"]'),
                ],
            );
        }

        return $lines;
    }

    private function text(SimpleXMLElement $element, string $xpath): string
    {
        $value = $this->nullableText($element, $xpath);

        return $value ?? '';
    }

    private function nullableText(SimpleXMLElement $element, string $xpath): ?string
    {
        $nodes = $element->xpath($xpath);

        if ($nodes === false || $nodes === []) {
            return null;
        }

        $value = mb_trim((string) $nodes[0]);

        return $value === '' ? null : $value;
    }

    private function attribute(SimpleXMLElement $element, string $xpath, string $attribute): ?string
    {
        $nodes = $element->xpath($xpath);

        if ($nodes === false || $nodes === []) {
            return null;
        }

        $value = mb_trim((string) $nodes[0]->attributes()->{$attribute});

        return $value === '' ? null : $value;
    }

    /**
     * @return numeric-string
     */
    private function signedAmount(string $amount, string $direction): string
    {
        $value = (float) str_replace(',', '.', $amount);

        if ($direction === 'DBIT') {
            $value *= -1;
        }

        return number_format(round($value, 4), 4, '.', '');
    }
}
