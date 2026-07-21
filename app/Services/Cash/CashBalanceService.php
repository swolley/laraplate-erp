<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Cash;

use DateTimeInterface;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\JournalEntryLine;
use Modules\ERP\Support\Decimal;

final readonly class CashBalanceService
{
    public function balance(Company $company, ?DateTimeInterface $through = null): string
    {
        $account_ids = Account::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('meta->erp_role', 'bank_cash')
            ->pluck('id');
        $query = JournalEntryLine::query()
            ->whereIn('account_id', $account_ids)
            ->whereHas('journal_entry', static function ($query) use ($company, $through): void {
                $query->withoutGlobalScopes()->where('company_id', $company->id)->whereNotNull('posted_at');

                if ($through !== null) {
                    $query->whereDate('posted_at', '<=', $through);
                }
            });

        return $query->pluck('amount_local')->reduce(
            static fn (string $total, mixed $amount): string => Decimal::add($total, (string) $amount),
            '0.0000',
        );
    }
}
