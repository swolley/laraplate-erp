<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\VatRegisterType;
use Modules\ERP\Casts\VatSettlementStatus;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\VatRegisterEntry;
use Modules\ERP\Models\VatSettlement;
use Modules\ERP\Support\ConnectionScopedTransaction;
use Modules\ERP\Support\ConnectionScopedModels;
use Modules\ERP\Support\Decimal;

final class VatSettlementService
{
    /**
     * @return array{vat_sales: string, vat_purchases: string, previous_credit: string, settlement_amount: string}
     */
    public function preview(int $company_id, int $fiscal_period_id): array
    {
        $fiscal_period = $this->fiscalPeriod($company_id, $fiscal_period_id);

        return $this->calculate(ConnectionScopedModels::for($fiscal_period), $company_id, $fiscal_period);
    }

    public function compute(int $company_id, int $fiscal_period_id): VatSettlement
    {
        $fiscal_period = $this->fiscalPeriod($company_id, $fiscal_period_id);

        return ConnectionScopedTransaction::run($fiscal_period, function (ConnectionScopedModels $models) use ($company_id, $fiscal_period_id, $fiscal_period): VatSettlement {
            $amounts = $this->calculate($models, $company_id, $fiscal_period);

            $existing = $models->query(VatSettlement::class)
                ->withoutGlobalScopes()
                ->where('company_id', $company_id)
                ->where('fiscal_period_id', $fiscal_period_id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->status === VatSettlementStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot recompute a confirmed settlement.'],
                ]);
            }

            return $models->query(VatSettlement::class)->updateOrCreate(
                ['company_id' => $company_id, 'fiscal_period_id' => $fiscal_period_id],
                [
                    ...$amounts,
                    'status' => VatSettlementStatus::Draft->value,
                ],
            );
        });
    }

    public function confirm(VatSettlement $settlement, int $user_id): void
    {
        if ($settlement->status === VatSettlementStatus::Confirmed) {
            throw ValidationException::withMessages([
                'status' => ['Settlement is already confirmed.'],
            ]);
        }

        ConnectionScopedTransaction::run($settlement, function (ConnectionScopedModels $models) use ($settlement, $user_id): void {
            $models->query(VatSettlement::class)->whereKey($settlement->getKey())->update([
                'status' => VatSettlementStatus::Confirmed->value,
                'confirmed_at' => CarbonImmutable::now(),
                'confirmed_by' => $user_id,
            ]);
        });
    }

    private function fiscalPeriod(int $company_id, int $fiscal_period_id): FiscalPeriod
    {
        return FiscalPeriod::query()
            ->whereKey($fiscal_period_id)
            ->whereHas('fiscal_year', static fn ($query) => $query->where('company_id', $company_id))
            ->firstOrFail();
    }

    /**
     * @return array{vat_sales: string, vat_purchases: string, previous_credit: string, settlement_amount: string}
     */
    private function calculate(ConnectionScopedModels $models, int $company_id, FiscalPeriod $fiscal_period): array
    {
        $fiscal_year_id = (int) $fiscal_period->fiscal_year_id;
        $vat_sales = (string) ($models->query(VatRegisterEntry::class)
            ->where('company_id', $company_id)
            ->where('fiscal_year_id', $fiscal_year_id)
            ->where('register_type', VatRegisterType::Sales->value)
            ->whereBetween('registration_date', [$fiscal_period->start_date, $fiscal_period->end_date])
            ->sum('tax_amount') ?? 0);
        $vat_purchases = (string) ($models->query(VatRegisterEntry::class)
            ->where('company_id', $company_id)
            ->where('fiscal_year_id', $fiscal_year_id)
            ->where('register_type', VatRegisterType::Purchases->value)
            ->whereBetween('registration_date', [$fiscal_period->start_date, $fiscal_period->end_date])
            ->sum('tax_amount') ?? 0);
        $previous_credit = '0.0000';
        $previous_period = $models->query(FiscalPeriod::class)
            ->where('fiscal_year_id', $fiscal_year_id)
            ->where('start_date', '<', $fiscal_period->start_date)
            ->latest('start_date')
            ->first();

        if ($previous_period !== null) {
            $previous_settlement = $models->query(VatSettlement::class)
                ->withoutGlobalScopes()
                ->where('company_id', $company_id)
                ->where('fiscal_period_id', $previous_period->id)
                ->where('status', VatSettlementStatus::Confirmed->value)
                ->first();

            if ($previous_settlement !== null && Decimal::isNegative((string) $previous_settlement->settlement_amount)) {
                $previous_credit = Decimal::abs((string) $previous_settlement->settlement_amount);
            }
        }

        return [
            'vat_sales' => Decimal::format($vat_sales),
            'vat_purchases' => Decimal::format($vat_purchases),
            'previous_credit' => $previous_credit,
            'settlement_amount' => Decimal::sub(Decimal::sub($vat_sales, $vat_purchases), $previous_credit),
        ];
    }
}
