<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Illuminate\Database\QueryException;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Exceptions\DocumentNumberAllocationRetryException;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Support\ConnectionScopedModels;
use Modules\ERP\Support\ConnectionScopedTransaction;

/**
 * Issues the next document number for a company stream, using a pessimistic row lock.
 */
final class DocumentNumberAllocator
{
    private const int MAX_RETRY_ATTEMPTS = 24;

    private const int RETRY_BASE_MICROSECONDS = 50000;

    /**
     * Allocates and returns the next display number (prefix, optional year, padded counter).
     *
     * @param  int  $fiscal_year  Four-digit year bucket, or 0 when the sequence is not fiscal-year scoped.
     */
    public function next(Company $company, DocumentType $document_type, int $fiscal_year = 0): string
    {
        for ($attempt = 1; $attempt <= self::MAX_RETRY_ATTEMPTS; $attempt++) {
            try {
                return ConnectionScopedTransaction::run($company, fn (ConnectionScopedModels $models): string => $this->allocate($models, $company, $document_type, $fiscal_year));
            } catch (QueryException $exception) {
                throw_unless(
                    $attempt < self::MAX_RETRY_ATTEMPTS && $this->isRetryableConcurrencyException($exception),
                    $exception,
                );

                $this->sleepBeforeRetry($attempt);
            } catch (DocumentNumberAllocationRetryException $exception) {
                throw_unless($attempt < self::MAX_RETRY_ATTEMPTS, $exception);

                $this->sleepBeforeRetry($attempt);
            }
        }

        throw new DocumentNumberAllocationRetryException('Unable to allocate the next document number after retrying.');
    }

    private function allocate(ConnectionScopedModels $models, Company $company, DocumentType $document_type, int $fiscal_year): string
    {
        $existing = $models->query(DocumentSequence::class)
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('document_type', $document_type->value)
            ->where('fiscal_year', $fiscal_year)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            return $this->incrementAndFormat($models, $existing, $fiscal_year);
        }

        try {
            $models->query(DocumentSequence::class)->withoutGlobalScopes()->create([
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
            throw_unless($this->isUniqueConstraintViolation($exception), $exception);

            $row = $models->query(DocumentSequence::class)
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('document_type', $document_type->value)
                ->where('fiscal_year', $fiscal_year)
                ->lockForUpdate()
                ->firstOrFail();

            return $this->incrementAndFormat($models, $row, $fiscal_year);
        }

        $inserted = $models->query(DocumentSequence::class)
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('document_type', $document_type->value)
            ->where('fiscal_year', $fiscal_year)
            ->firstOrFail();

        return DocumentNumberFormatter::format($inserted, $fiscal_year, $inserted->last_number);
    }

    private function incrementAndFormat(ConnectionScopedModels $models, DocumentSequence $row, int $fiscal_year): string
    {
        $current_number = $row->last_number;
        $next_number = $current_number + 1;
        $updated = $models->query(DocumentSequence::class)
            ->withoutGlobalScopes()
            ->whereKey($row->id)
            ->where('last_number', $current_number)
            ->update([
                'last_number' => $next_number,
                'updated_at' => now(),
            ]);

        throw_unless($updated === 1, new DocumentNumberAllocationRetryException(
            'Concurrent document sequence update lost the compare-and-swap race.',
        ));

        return DocumentNumberFormatter::format($row, $fiscal_year, $next_number);
    }

    private function sleepBeforeRetry(int $attempt): void
    {
        usleep(self::RETRY_BASE_MICROSECONDS * $attempt);
    }

    private function isRetryableConcurrencyException(QueryException $exception): bool
    {
        $sql_state = (string) ($exception->errorInfo[0] ?? '');
        $driver_code = (string) ($exception->errorInfo[1] ?? '');
        $message = mb_strtolower($exception->getMessage());

        return in_array($sql_state, ['40001', '40P01', '55P03'], true)
            || in_array($driver_code, ['5', '1205', '1213'], true)
            || str_contains($message, 'database is locked')
            || str_contains($message, 'database table is locked')
            || str_contains($message, 'deadlock found')
            || str_contains($message, 'lock wait timeout');
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sql_state = (string) ($exception->errorInfo[0] ?? '');
        $message = $exception->getMessage();

        return in_array($sql_state, ['23000', '23505'], true)
            || str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'duplicate key value violates unique constraint');
    }
}
