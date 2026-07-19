<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Illuminate\Support\Collection;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\SalesOrder;

final class DocumentSequenceAuditService
{
    /**
     * @return array{company_id: int, year: int, checks: list<array{sequence_id: int|null, document_type: string, status: 'success'|'warning'|'failure', code: string, message: string}>, summary: array{success: int, warning: int, failure: int}}
     */
    public function audit(int $company_id, int $year): array
    {
        $sequences = DocumentSequence::query()->withoutGlobalScopes()
            ->where('company_id', $company_id)
            ->whereIn('fiscal_year', [$year, 0])
            ->orderBy('fiscal_year')
            ->orderBy('document_type')
            ->get();

        $checks = [];

        foreach ($sequences as $sequence) {
            $this->auditSequence($sequence, $checks);
        }

        $this->auditMissingSequences($company_id, $year, $sequences, $checks);

        return [
            'company_id' => $company_id,
            'year' => $year,
            'checks' => $checks,
            'summary' => [
                'success' => $this->countStatus($checks, 'success'),
                'warning' => $this->countStatus($checks, 'warning'),
                'failure' => $this->countStatus($checks, 'failure'),
            ],
        ];
    }

    /**
     * @param  list<array{sequence_id: int|null, document_type: string, status: 'success'|'warning'|'failure', code: string, message: string}>  $checks
     */
    private function auditSequence(DocumentSequence $sequence, array &$checks): void
    {
        $document_type = $sequence->document_type;

        if (! $document_type instanceof DocumentType || ! $this->isAuditable($document_type)) {
            $this->add($checks, $sequence, 'warning', 'stream_not_auditable', sprintf(
                'Stream [%s] has no persisted document reference mapping and cannot be cross-checked.',
                $document_type instanceof DocumentType ? $document_type->value : (string) $document_type,
            ));

            return;
        }

        if ($this->numberTokenCount($sequence) !== 1) {
            $this->add($checks, $sequence, 'failure', 'invalid_format_pattern', 'Sequence format must contain the {number} token exactly once.');

            return;
        }

        $references = $this->referencesFor($document_type, (int) $sequence->company_id, (int) $sequence->fiscal_year);
        $issues_before = count($checks);
        $duplicates = $references->countBy()->filter(static fn (int $count): bool => $count > 1);

        if ($duplicates->isNotEmpty()) {
            $this->add($checks, $sequence, 'failure', 'duplicate_references', sprintf(
                'Duplicate references found: %s.',
                $duplicates->map(static fn (int $count, string $reference): string => sprintf('%s (%dx)', $reference, $count))->implode(', '),
            ));
        }

        $counters = [];
        $malformed = [];

        foreach ($references->unique()->values() as $reference) {
            $counter = $this->counterFromReference($sequence, $reference);

            if ($counter === null) {
                $malformed[] = $reference;

                continue;
            }

            $counters[] = $counter;
        }

        if ($malformed !== []) {
            $this->add($checks, $sequence, 'warning', 'unrecognized_references', sprintf(
                '%d references do not match the configured formatter: %s.',
                count($malformed),
                implode(', ', array_slice($malformed, 0, 10)),
            ));
        }

        $counters = array_values(array_unique($counters));
        sort($counters);
        $max_counter = $counters === [] ? 0 : max($counters);

        if ($max_counter > $sequence->last_number) {
            $this->add($checks, $sequence, 'failure', 'counter_behind_documents', sprintf(
                'Sequence last_number is %d but persisted documents reach %d.',
                $sequence->last_number,
                $max_counter,
            ));
        } elseif ($sequence->last_number > $max_counter) {
            $this->add($checks, $sequence, 'warning', 'counter_ahead_of_documents', sprintf(
                'Sequence last_number is %d while the highest recognized document is %d; verify deliberate reservations.',
                $sequence->last_number,
                $max_counter,
            ));
        }

        if (! $sequence->gap_allowed && $counters !== []) {
            [$missing_count, $missing_ranges] = $this->missingRanges($counters);

            if ($missing_count > 0) {
                $this->add($checks, $sequence, 'warning', 'sequence_gaps', sprintf(
                    '%d counters are missing in a gap-free stream: %s.',
                    $missing_count,
                    implode(', ', array_slice($missing_ranges, 0, 10)),
                ));
            }
        }

        if (count($checks) === $issues_before) {
            $this->add($checks, $sequence, 'success', 'sequence_consistent', sprintf(
                'Sequence [%s/%d] is consistent through counter %d.',
                $document_type->value,
                $sequence->fiscal_year,
                $sequence->last_number,
            ));
        }
    }

    /**
     * @param  Collection<int, DocumentSequence>  $sequences
     * @param  list<array{sequence_id: int|null, document_type: string, status: 'success'|'warning'|'failure', code: string, message: string}>  $checks
     */
    private function auditMissingSequences(int $company_id, int $year, Collection $sequences, array &$checks): void
    {
        foreach ($this->auditableTypes() as $document_type) {
            $fiscal_year = $this->isFiscal($document_type) ? $year : 0;
            $exists = $sequences->contains(static fn (DocumentSequence $sequence): bool => $sequence->document_type === $document_type
                && $sequence->fiscal_year === $fiscal_year);

            if ($exists) {
                continue;
            }

            $references = $this->referencesFor($document_type, $company_id, $fiscal_year);

            if ($references->isNotEmpty()) {
                $checks[] = [
                    'sequence_id' => null,
                    'document_type' => $document_type->value,
                    'status' => 'warning',
                    'code' => 'missing_sequence',
                    'message' => sprintf('%d persisted documents use stream [%s/%d], but its sequence row is missing.', $references->count(), $document_type->value, $fiscal_year),
                ];
            }
        }
    }

    /**
     * @return Collection<int, string>
     */
    private function referencesFor(DocumentType $document_type, int $company_id, int $fiscal_year): Collection
    {
        if ($document_type === DocumentType::SalesOrder) {
            return $this->referencesFromModel(SalesOrder::class, $company_id);
        }

        if ($document_type === DocumentType::PurchaseOrder) {
            return $this->referencesFromModel(PurchaseOrder::class, $company_id);
        }

        [$direction, $invoice_type] = match ($document_type) {
            DocumentType::SalesInvoice => [InvoiceDirection::Sale, InvoiceType::Invoice],
            DocumentType::PurchaseInvoice => [InvoiceDirection::Purchase, InvoiceType::Invoice],
            DocumentType::SalesCreditNote => [InvoiceDirection::Sale, InvoiceType::CreditNote],
            DocumentType::PurchaseCreditNote => [InvoiceDirection::Purchase, InvoiceType::CreditNote],
            DocumentType::SalesDebitNote => [InvoiceDirection::Sale, InvoiceType::DebitNote],
            DocumentType::PurchaseDebitNote => [InvoiceDirection::Purchase, InvoiceType::DebitNote],
            default => [null, null],
        };

        if (! $direction instanceof InvoiceDirection || ! $invoice_type instanceof InvoiceType) {
            return collect();
        }

        return Invoice::query()->withoutGlobalScopes()
            ->where('company_id', $company_id)
            ->where('direction', $direction->value)
            ->where('invoice_type', $invoice_type->value)
            ->whereNotNull('posted_at')
            ->whereYear('posted_at', $fiscal_year)
            ->whereNotNull('reference')
            ->pluck('reference')
            ->map(static fn (mixed $reference): string => (string) $reference)
            ->values();
    }

    /**
     * @param  class-string<SalesOrder|PurchaseOrder>  $model
     * @return Collection<int, string>
     */
    private function referencesFromModel(string $model, int $company_id): Collection
    {
        return $model::query()->withoutGlobalScopes()
            ->where('company_id', $company_id)
            ->whereNotNull('reference')
            ->pluck('reference')
            ->map(static fn (mixed $reference): string => (string) $reference)
            ->values();
    }

    private function counterFromReference(DocumentSequence $sequence, string $reference): ?int
    {
        $template = $this->formatTemplate($sequence);
        $sentinels = [
            '{prefix}' => '___ERP_PREFIX___',
            '{suffix}' => '___ERP_SUFFIX___',
            '{YYYY}' => '___ERP_YEAR___',
            '{number}' => '___ERP_NUMBER___',
        ];
        $quoted = preg_quote(str_replace(array_keys($sentinels), array_values($sentinels), $template), '~');
        $regex = str_replace(
            array_values($sentinels),
            [
                preg_quote($sequence->prefix, '~'),
                preg_quote($sequence->suffix, '~'),
                $sequence->fiscal_year > 0 ? preg_quote((string) $sequence->fiscal_year, '~') : '',
                '(?<counter>[0-9]+)',
            ],
            $quoted,
        );

        if (preg_match('~^' . $regex . '$~u', $reference, $matches) !== 1) {
            return null;
        }

        $counter = (int) $matches['counter'];

        if ($counter < 1 || DocumentNumberFormatter::format($sequence, $sequence->fiscal_year, $counter) !== $reference) {
            return null;
        }

        return $counter;
    }

    private function formatTemplate(DocumentSequence $sequence): string
    {
        if (filled($sequence->format_pattern)) {
            return (string) $sequence->format_pattern;
        }

        return $sequence->fiscal_year > 0
            ? '{prefix}{YYYY}-{number}{suffix}'
            : '{prefix}{number}{suffix}';
    }

    private function numberTokenCount(DocumentSequence $sequence): int
    {
        return substr_count($this->formatTemplate($sequence), '{number}');
    }

    /**
     * @param  list<int>  $counters
     * @return array{0: int, 1: list<string>}
     */
    private function missingRanges(array $counters): array
    {
        $missing_count = 0;
        $ranges = [];
        $previous = 0;

        foreach ($counters as $counter) {
            if ($counter > $previous + 1) {
                $from = $previous + 1;
                $to = $counter - 1;
                $missing_count += $to - $from + 1;
                $ranges[] = $from === $to ? (string) $from : sprintf('%d-%d', $from, $to);
            }

            $previous = $counter;
        }

        return [$missing_count, $ranges];
    }

    /**
     * @return list<DocumentType>
     */
    private function auditableTypes(): array
    {
        return [
            DocumentType::SalesOrder,
            DocumentType::PurchaseOrder,
            DocumentType::SalesInvoice,
            DocumentType::PurchaseInvoice,
            DocumentType::SalesCreditNote,
            DocumentType::PurchaseCreditNote,
            DocumentType::SalesDebitNote,
            DocumentType::PurchaseDebitNote,
        ];
    }

    private function isAuditable(DocumentType $document_type): bool
    {
        return in_array($document_type, $this->auditableTypes(), true);
    }

    private function isFiscal(DocumentType $document_type): bool
    {
        return ! in_array($document_type, [DocumentType::SalesOrder, DocumentType::PurchaseOrder], true);
    }

    /**
     * @param  list<array{sequence_id: int|null, document_type: string, status: 'success'|'warning'|'failure', code: string, message: string}>  $checks
     */
    private function add(array &$checks, DocumentSequence $sequence, string $status, string $code, string $message): void
    {
        $checks[] = [
            'sequence_id' => (int) $sequence->getKey(),
            'document_type' => $sequence->document_type instanceof DocumentType ? $sequence->document_type->value : (string) $sequence->document_type,
            'status' => $status,
            'code' => $code,
            'message' => $message,
        ];
    }

    /**
     * @param  list<array{sequence_id: int|null, document_type: string, status: 'success'|'warning'|'failure', code: string, message: string}>  $checks
     */
    private function countStatus(array $checks, string $status): int
    {
        return count(array_filter($checks, static fn (array $check): bool => $check['status'] === $status));
    }
}
