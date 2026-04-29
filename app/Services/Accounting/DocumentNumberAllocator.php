<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;

/**
 * Issues the next document number for a company stream, using a pessimistic row lock.
 */
final class DocumentNumberAllocator
{
    /**
     * Allocates and returns the next display number (prefix, optional year, padded counter).
     *
     * @param  int  $fiscal_year  Four-digit year bucket, or 0 when the sequence is not fiscal-year scoped.
     */
    public function next(Company $company, DocumentType $document_type, int $fiscal_year = 0): string
    {
        return DB::transaction(function () use ($company, $document_type, $fiscal_year): string {
            $existing = DocumentSequence::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('document_type', $document_type->value)
                ->where('fiscal_year', $fiscal_year)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return self::incrementAndFormat($existing, $fiscal_year);
            }

            try {
                DocumentSequence::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'document_type' => $document_type,
                    'fiscal_year' => $fiscal_year,
                    'last_number' => 1,
                    'gap_allowed' => $document_type->defaultGapAllowed(),
                    'prefix' => '',
                    'padding' => 5,
                    'format_pattern' => null,
                    'suffix' => '',
                ]);
            } catch (QueryException $exception) {
                if (! self::isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                $row = DocumentSequence::query()
                    ->withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('document_type', $document_type->value)
                    ->where('fiscal_year', $fiscal_year)
                    ->lockForUpdate()
                    ->firstOrFail();

                return self::incrementAndFormat($row, $fiscal_year);
            }

            $inserted = DocumentSequence::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('document_type', $document_type->value)
                ->where('fiscal_year', $fiscal_year)
                ->firstOrFail();

            return DocumentNumberFormatter::format($inserted, $fiscal_year, $inserted->last_number);
        });
    }

    private static function incrementAndFormat(DocumentSequence $row, int $fiscal_year): string
    {
        DocumentSequence::query()->withoutGlobalScopes()->whereKey($row->id)->update([
            'last_number' => DB::raw('last_number + 1'),
            'updated_at' => now(),
        ]);
        $row->refresh();

        return DocumentNumberFormatter::format($row, $fiscal_year, $row->last_number);
    }

    private static function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sql_state = (string) ($exception->errorInfo[0] ?? '');

        return $sql_state === '23000' || str_contains($exception->getMessage(), 'UNIQUE constraint failed');
    }
}
