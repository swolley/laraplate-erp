<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\VatRegisterType;
use Modules\ERP\Casts\VatSettlementStatus;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\VatRegisterEntry;
use Modules\ERP\Models\VatSettlement;

final class VatSettlementService
{
    public function compute(int $company_id, int $fiscal_period_id): VatSettlement
    {
        return DB::transaction(function () use ($company_id, $fiscal_period_id): VatSettlement {
            $fiscal_period = FiscalPeriod::query()->findOrFail($fiscal_period_id);
            $fiscal_year_id = (int) $fiscal_period->fiscal_year_id;

            $vat_sales = (string) (VatRegisterEntry::query()
                ->where('company_id', $company_id)
                ->where('fiscal_year_id', $fiscal_year_id)
                ->where('register_type', VatRegisterType::Sales->value)
                ->whereBetween('registration_date', [
                    $fiscal_period->start_date,
                    $fiscal_period->end_date,
                ])
                ->sum('tax_amount') ?? 0);

            $vat_purchases = (string) (VatRegisterEntry::query()
                ->where('company_id', $company_id)
                ->where('fiscal_year_id', $fiscal_year_id)
                ->where('register_type', VatRegisterType::Purchases->value)
                ->whereBetween('registration_date', [
                    $fiscal_period->start_date,
                    $fiscal_period->end_date,
                ])
                ->sum('tax_amount') ?? 0);

            $previous_credit = '0.0000';

            $previous_period = FiscalPeriod::query()
                ->where('fiscal_year_id', $fiscal_year_id)
                ->where('start_date', '<', $fiscal_period->start_date)
                ->latest('start_date')
                ->first();

            if ($previous_period !== null) {
                $previous_settlement = VatSettlement::query()
                    ->withoutGlobalScopes()
                    ->where('company_id', $company_id)
                    ->where('fiscal_period_id', (int) $previous_period->id)
                    ->where('status', VatSettlementStatus::Confirmed->value)
                    ->first();

                if ($previous_settlement !== null && (float) $previous_settlement->settlement_amount < 0) {
                    $previous_credit = $this->round4(abs((float) $previous_settlement->settlement_amount));
                }
            }

            $settlement_amount = $this->round4(
                (float) $vat_sales - (float) $vat_purchases - (float) $previous_credit,
            );

            $existing = VatSettlement::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company_id)
                ->where('fiscal_period_id', $fiscal_period_id)
                ->first();

            if ($existing !== null && $existing->status === VatSettlementStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot recompute a confirmed settlement.'],
                ]);
            }

            return VatSettlement::query()->updateOrCreate(
                ['company_id' => $company_id, 'fiscal_period_id' => $fiscal_period_id],
                [
                    'vat_sales' => $this->round4((float) $vat_sales),
                    'vat_purchases' => $this->round4((float) $vat_purchases),
                    'previous_credit' => $previous_credit,
                    'settlement_amount' => $settlement_amount,
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

        $settlement->update([
            'status' => VatSettlementStatus::Confirmed->value,
            'confirmed_at' => CarbonImmutable::now(),
            'confirmed_by' => $user_id,
        ]);
    }

    private function round4(float $value): string
    {
        return number_format(round($value, 4), 4, '.', '');
    }
}
