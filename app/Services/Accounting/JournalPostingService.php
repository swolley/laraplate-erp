<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\ERP\Exceptions\CannotReverseUnpostedJournalException;
use Modules\ERP\Exceptions\FiscalPeriodCompanyMismatchException;
use Modules\ERP\Exceptions\JournalAccountNotInCompanyException;
use Modules\ERP\Exceptions\JournalAlreadyReversedException;
use Modules\ERP\Exceptions\JournalEntryCompanyMismatchException;
use Modules\ERP\Exceptions\PostingToClosedFiscalPeriodException;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\JournalEntryLine;

/**
 * Persists balanced double-entry journal entries in the company functional currency.
 */
final class JournalPostingService
{
    /**
     * @param  list<array{
     *     account_id: int,
     *     amount_doc: string|float,
     *     currency_doc: string,
     *     amount_local: string|float,
     *     currency_local: string,
     *     fx_rate: string|float,
     *     description?: string|null,
     *     tax_code?: string|null,
     *     tax_rate?: string|float|null,
     *     tax_label?: string|null,
     * }>  $lines
     * @param  Model|null  $reference  Optional morph source stored on the journal header.
     */
    public function post(
        Company $company,
        array $lines,
        ?FiscalPeriod $fiscal_period = null,
        ?string $description = null,
        ?int $posted_by_user_id = null,
        ?Model $reference = null,
        ?CarbonImmutable $posted_at = null,
    ): JournalEntry {
        $this->validateLinesForPosting($company, $lines, $fiscal_period);

        $posted_at ??= CarbonImmutable::now();

        return DB::transaction(fn (): JournalEntry => $this->persistPostedEntry(
            $company,
            $lines,
            $fiscal_period,
            $description,
            $posted_by_user_id,
            $posted_at,
            null,
            null,
            $reference,
        ));
    }

    /**
     * Posts a reversing voucher that inverts every line amount of a posted entry.
     */
    public function reverse(
        JournalEntry $posted_entry,
        Company $company,
        string $reversal_reason,
        ?int $posted_by_user_id = null,
    ): JournalEntry {
        $posted_entry = JournalEntry::query()->withoutGlobalScopes()
            ->whereKey($posted_entry->id)
            ->with('lines')
            ->firstOrFail();

        if ($posted_entry->posted_at === null) {
            throw CannotReverseUnpostedJournalException::make($this->modelId($posted_entry));
        }

        if ($posted_entry->company_id !== $this->modelId($company)) {
            throw JournalEntryCompanyMismatchException::make(
                $this->modelId($posted_entry),
                $this->modelId($company),
            );
        }

        $reversal_exists = JournalEntry::query()->withoutGlobalScopes()
            ->where('reverses_journal_entry_id', $posted_entry->id)
            ->exists();

        if ($reversal_exists) {
            throw JournalAlreadyReversedException::make($this->modelId($posted_entry));
        }

        $storno_lines = [];

        foreach ($posted_entry->lines as $line) {
            $storno_lines[] = [
                'account_id' => $line->account_id,
                'amount_doc' => JournalLineBalance::negated($line->amount_doc),
                'currency_doc' => $line->currency_doc,
                'amount_local' => JournalLineBalance::negated($line->amount_local),
                'currency_local' => $line->currency_local,
                'fx_rate' => $line->fx_rate,
                'tax_code' => $line->tax_code,
                'tax_rate' => $line->tax_rate,
                'tax_label' => $line->tax_label,
                'description' => $line->description,
            ];
        }

        $this->validateLinesForPosting($company, $storno_lines, $posted_entry->fiscal_period);
        $posted_at = CarbonImmutable::now();
        $description = 'Reversal of journal #' . (string) $posted_entry->id . ': ' . $reversal_reason;

        return DB::transaction(fn (): JournalEntry => $this->persistPostedEntry(
            $company,
            $storno_lines,
            $posted_entry->fiscal_period,
            $description,
            $posted_by_user_id,
            $posted_at,
            $this->modelId($posted_entry),
            $reversal_reason,
        ));
    }

    /**
     * @param  list<array{
     *     account_id: int,
     *     amount_doc: string|float,
     *     currency_doc: string,
     *     amount_local: string|float,
     *     currency_local: string,
     *     fx_rate: string|float,
     *     description?: string|null,
     *     tax_code?: string|null,
     *     tax_rate?: string|float|null,
     *     tax_label?: string|null,
     * }>  $lines
     */
    private function persistPostedEntry(
        Company $company,
        array $lines,
        ?FiscalPeriod $fiscal_period,
        ?string $description,
        ?int $posted_by_user_id,
        CarbonImmutable $posted_at,
        ?int $reverses_journal_entry_id,
        ?string $reversal_reason,
        ?Model $reference = null,
    ): JournalEntry {
        $entry = JournalEntry::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'fiscal_period_id' => $fiscal_period?->getKey(),
            'posted_at' => $posted_at,
            'posted_by' => $posted_by_user_id,
            'description' => $description,
            'reverses_journal_entry_id' => $reverses_journal_entry_id,
            'reversal_reason' => $reversal_reason,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
        ]);

        $line_no = 1;

        foreach ($lines as $line) {
            JournalEntryLine::query()->create([
                'journal_entry_id' => $entry->getKey(),
                'line_no' => $line_no,
                'account_id' => $line['account_id'],
                'amount_doc' => $line['amount_doc'],
                'currency_doc' => $line['currency_doc'],
                'amount_local' => $line['amount_local'],
                'currency_local' => $line['currency_local'],
                'fx_rate' => $line['fx_rate'],
                'tax_code' => $line['tax_code'] ?? null,
                'tax_rate' => $line['tax_rate'] ?? null,
                'tax_label' => $line['tax_label'] ?? null,
                'description' => $line['description'] ?? null,
            ]);
            $line_no++;
        }

        $this->assertPersistedLinesBalance($entry);

        return $entry->load('lines');
    }

    /**
     * @param  list<array{
     *     account_id: int,
     *     amount_doc: string|float,
     *     currency_doc: string,
     *     amount_local: string|float,
     *     currency_local: string,
     *     fx_rate: string|float,
     *     description?: string|null,
     *     tax_code?: string|null,
     *     tax_rate?: string|float|null,
     *     tax_label?: string|null,
     * }>  $lines
     */
    private function validateLinesForPosting(Company $company, array $lines, ?FiscalPeriod $fiscal_period): void
    {
        $amount_locals = array_map(
            static fn (array $line): string|float => $line['amount_local'],
            $lines,
        );
        JournalLineBalance::assertBalanced($amount_locals);

        if ($fiscal_period instanceof FiscalPeriod) {
            if ($fiscal_period->is_closed) {
                throw PostingToClosedFiscalPeriodException::forPeriod($this->modelId($fiscal_period));
            }

            $fiscal_period->loadMissing('fiscal_year');
            $year = $fiscal_period->fiscal_year;

            if ($year !== null && $year->company_id !== $this->modelId($company)) {
                throw FiscalPeriodCompanyMismatchException::make(
                    $this->modelId($fiscal_period),
                    $this->modelId($company),
                );
            }
        }

        foreach ($lines as $line) {
            $this->assertAccountBelongsToCompany($line['account_id'], $company);
        }
    }

    private function assertPersistedLinesBalance(JournalEntry $entry): void
    {
        /** @var list<string|float> $amount_locals */
        $amount_locals = [];

        foreach (JournalEntryLine::query()->where('journal_entry_id', $entry->id)->cursor() as $line) {
            $amount_locals[] = $line->amount_local;
        }

        JournalLineBalance::assertBalanced($amount_locals);
    }

    private function assertAccountBelongsToCompany(int $account_id, Company $company): void
    {
        $exists = Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereKey($account_id)
            ->exists();

        if (! $exists) {
            throw JournalAccountNotInCompanyException::forAccount($account_id, $this->modelId($company));
        }
    }

    private function modelId(Company|FiscalPeriod|JournalEntry $model): int
    {
        return is_int($model->id) ? $model->id : (int) $model->id;
    }
}
