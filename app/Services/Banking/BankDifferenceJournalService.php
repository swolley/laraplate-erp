<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Banking;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\BankStatementLine;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\Payment;
use Modules\ERP\Services\Accounting\JournalPostingService;

final readonly class BankDifferenceJournalService
{
    public function __construct(
        private JournalPostingService $journal_posting_service,
    ) {}

    public function postDifference(
        Company $company,
        BankStatementLine $line,
        Payment $payment,
        string $difference_amount_doc,
        int $expense_account_id,
        int $bank_account_id,
    ): JournalEntry {
        if ($bank_account_id !== (int) $line->bank_statement?->bank_account_id) {
            throw ValidationException::withMessages([
                'bank_account_id' => ['The bank statement line does not belong to the selected bank account.'],
            ]);
        }

        $bank_cash = $this->findAccountByRole($company, 'bank_cash');
        $expense = $this->expenseAccount($company, $expense_account_id);
        $amount_doc = $this->round4((float) $difference_amount_doc);

        if (abs((float) $amount_doc) <= 0.00005) {
            throw ValidationException::withMessages([
                'amount_doc' => ['Bank reconciliation difference amount must not be zero.'],
            ]);
        }

        $description = sprintf(
            'Bank reconciliation difference for payment #%s and statement line #%s',
            (string) $payment->id,
            (string) $line->id,
        );

        return $this->journal_posting_service->post(
            $company,
            [
                $this->journalLine((int) $bank_cash->id, $amount_doc, $line->currency_doc, (string) $line->fx_rate, 'Bank cash difference'),
                $this->journalLine((int) $expense->id, $this->negate($amount_doc), $line->currency_doc, (string) $line->fx_rate, 'Bank reconciliation difference'),
            ],
            $this->resolveFiscalPeriod($company, CarbonImmutable::parse($line->booked_at)),
            $description,
            null,
            $line,
            CarbonImmutable::parse($line->booked_at),
        );
    }

    private function findAccountByRole(Company $company, string $role): Account
    {
        $account = Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('meta->erp_role', $role)
            ->first();

        if ($account !== null) {
            return $account;
        }

        throw ValidationException::withMessages([
            'accounts' => ['Chart of accounts missing role: ' . $role],
        ]);
    }

    private function expenseAccount(Company $company, int $expense_account_id): Account
    {
        $account = Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereKey($expense_account_id)
            ->where('kind', AccountKind::Expense->value)
            ->where('is_active', true)
            ->first();

        if ($account !== null) {
            return $account;
        }

        throw ValidationException::withMessages([
            'expense_account_id' => ['The difference account does not belong to the company.'],
        ]);
    }

    private function resolveFiscalPeriod(Company $company, CarbonImmutable $posted_at): ?FiscalPeriod
    {
        return FiscalPeriod::query()
            ->withoutGlobalScopes()
            ->whereHas('fiscal_year', static function (Builder $query) use ($company): void {
                $query->withoutGlobalScopes()
                    ->where('company_id', $company->id);
            })
            ->whereDate('start_date', '<=', $posted_at)
            ->whereDate('end_date', '>=', $posted_at)
            ->first();
    }

    /**
     * @return array{
     *     account_id: int,
     *     amount_doc: string,
     *     currency_doc: string,
     *     amount_local: string,
     *     currency_local: string,
     *     fx_rate: string,
     *     description: string,
     * }
     */
    private function journalLine(int $account_id, string $amount_doc, string $currency, string $fx_rate, string $description): array
    {
        return [
            'account_id' => $account_id,
            'amount_doc' => $amount_doc,
            'currency_doc' => $currency,
            'amount_local' => $amount_doc,
            'currency_local' => $currency,
            'fx_rate' => $fx_rate,
            'description' => $description,
        ];
    }

    /**
     * @return numeric-string
     */
    private function round4(float $value): string
    {
        return number_format(round($value, 4), 4, '.', '');
    }

    /**
     * @return numeric-string
     */
    private function negate(string $value): string
    {
        return $this->round4(-1 * (float) $value);
    }
}
