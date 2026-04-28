<?php

declare(strict_types=1);

namespace Modules\Business\Services\Accounting;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Business\Exceptions\FiscalPeriodCompanyMismatchException;
use Modules\Business\Exceptions\JournalAccountNotInCompanyException;
use Modules\Business\Exceptions\PostingToClosedFiscalPeriodException;
use Modules\Business\Models\Account;
use Modules\Business\Models\Company;
use Modules\Business\Models\FiscalPeriod;
use Modules\Business\Models\JournalEntry;
use Modules\Business\Models\JournalEntryLine;

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
     */
    public function post(
        Company $company,
        array $lines,
        ?FiscalPeriod $fiscal_period = null,
        ?string $description = null,
        ?int $posted_by_user_id = null,
    ): JournalEntry {
        $amount_locals = array_map(
            static fn (array $line): string|float => $line['amount_local'],
            $lines,
        );
        JournalLineBalance::assertBalanced($amount_locals);

        if ($fiscal_period !== null) {
            if ($fiscal_period->is_closed) {
                throw PostingToClosedFiscalPeriodException::forPeriod((int) $fiscal_period->getKey());
            }

            $fiscal_period->loadMissing('fiscal_year');
            $year = $fiscal_period->fiscal_year;

            if ($year !== null && (int) $year->company_id !== (int) $company->id) {
                throw FiscalPeriodCompanyMismatchException::make(
                    (int) $fiscal_period->getKey(),
                    (int) $company->id,
                );
            }
        }

        foreach ($lines as $line) {
            $this->assertAccountBelongsToCompany((int) $line['account_id'], $company);
        }

        $posted_at = CarbonImmutable::now();

        return DB::transaction(function () use (
            $company,
            $lines,
            $fiscal_period,
            $description,
            $posted_by_user_id,
            $posted_at,
        ): JournalEntry {
            $entry = JournalEntry::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'fiscal_period_id' => $fiscal_period?->getKey(),
                'posted_at' => $posted_at,
                'posted_by' => $posted_by_user_id,
                'description' => $description,
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

            return $entry->load('lines');
        });
    }

    private function assertAccountBelongsToCompany(int $account_id, Company $company): void
    {
        $exists = Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereKey($account_id)
            ->exists();

        if (! $exists) {
            throw JournalAccountNotInCompanyException::forAccount($account_id, (int) $company->id);
        }
    }
}
