<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Cash;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Casts\MovementType;
use Modules\ERP\Contracts\CurrencyConverter;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\Movement;
use Modules\ERP\Services\Accounting\JournalPostingService;
use Modules\ERP\Support\Decimal;
use Modules\ERP\ValueObjects\Money;

final readonly class MovementPostingService
{
    public function __construct(
        private JournalPostingService $journal_posting_service,
        private CurrencyConverter $currency_converter,
    ) {}

    public function post(Movement $movement): JournalEntry
    {
        return DB::transaction(function () use ($movement): JournalEntry {
            $locked = Movement::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($movement->id);

            if ($locked->posted_journal_entry_id !== null) {
                return JournalEntry::query()->withoutGlobalScopes()->findOrFail($locked->posted_journal_entry_id);
            }

            $company = Company::query()->withoutGlobalScopes()->findOrFail($locked->company_id);
            $counterparty = Account::query()->withoutGlobalScopes()->findOrFail($locked->counterparty_account_id);
            $this->assertCounterparty($locked, $counterparty);
            $bank_cash = $this->bankCashAccount($company);
            $occurred_on = CarbonImmutable::parse($locked->occurred_on);
            $conversion = $this->currency_converter->convert(
                strtoupper((string) $locked->currency_doc),
                strtoupper((string) $company->default_currency),
                (string) $locked->amount_doc,
                $occurred_on,
            );
            $currency_doc = strtoupper((string) $locked->currency_doc);
            $currency_local = strtoupper((string) $company->default_currency);
            $amount_doc = Money::of((string) $locked->amount_doc, $currency_doc)->amount;
            $amount_local = Money::of((string) $conversion['amount'], $currency_local)->amount;
            $fx_rate = number_format($conversion['rate'], 8, '.', '');
            $description = $locked->description ?: 'Cash movement #' . (string) $locked->id;
            $lines = $this->journalLines($locked, $counterparty, $bank_cash, $amount_doc, $amount_local, $currency_local, $fx_rate, $description);
            $entry = $this->journal_posting_service->post(
                $company,
                $lines,
                $this->fiscalPeriod($company, $occurred_on),
                $description,
                null,
                $locked,
                $occurred_on,
            );

            $locked->amount_local = $amount_local;
            $locked->currency_local = $currency_local;
            $locked->fx_rate = $fx_rate;
            $locked->posted_journal_entry_id = (int) $entry->id;
            $locked->save();

            return $entry;
        });
    }

    private function assertCounterparty(Movement $movement, Account $account): void
    {
        $expected_kind = $movement->type === MovementType::Income ? AccountKind::Revenue : AccountKind::Expense;

        if ((int) $account->company_id !== (int) $movement->company_id || $account->kind !== $expected_kind || ! $account->is_active) {
            throw ValidationException::withMessages([
                'counterparty_account_id' => [sprintf(
                    '%s movements require an active %s account from the same company.',
                    ucfirst($movement->type->value),
                    $expected_kind->value,
                )],
            ]);
        }
    }

    private function bankCashAccount(Company $company): Account
    {
        $account = Account::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('meta->erp_role', 'bank_cash')
            ->where('is_active', true)
            ->first();

        if (! $account instanceof Account) {
            throw ValidationException::withMessages(['accounts' => ['Chart of accounts missing active role: bank_cash']]);
        }

        return $account;
    }

    /** @return list<array<string, int|string>> */
    private function journalLines(Movement $movement, Account $counterparty, Account $bank_cash, string $amount_doc, string $amount_local, string $currency_local, string $fx_rate, string $description): array
    {
        $base = fn (Account $account, string $doc, string $local): array => [
            'account_id' => (int) $account->id,
            'amount_doc' => $doc,
            'currency_doc' => strtoupper((string) $movement->currency_doc),
            'amount_local' => $local,
            'currency_local' => $currency_local,
            'fx_rate' => $fx_rate,
            'description' => $description,
        ];

        if ($movement->type === MovementType::Income) {
            return [$base($bank_cash, $amount_doc, $amount_local), $base($counterparty, Decimal::negate($amount_doc), Decimal::negate($amount_local))];
        }

        return [$base($counterparty, $amount_doc, $amount_local), $base($bank_cash, Decimal::negate($amount_doc), Decimal::negate($amount_local))];
    }

    private function fiscalPeriod(Company $company, CarbonImmutable $date): ?FiscalPeriod
    {
        return FiscalPeriod::query()->withoutGlobalScopes()
            ->whereHas('fiscal_year', static fn ($query) => $query->withoutGlobalScopes()->where('company_id', $company->id))
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }
}
