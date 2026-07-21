<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\VatSettlementStatus;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\VatSettlement;
use Throwable;

final readonly class VatSettlementBatchService
{
    public function __construct(private VatSettlementService $settlement_service) {}

    /**
     * @return array{company_id: int, year: int, periods: list<array{fiscal_period_id: int, period: string, status: 'computed'|'preview'|'skipped'|'failed', vat_sales: string|null, vat_purchases: string|null, previous_credit: string|null, settlement_amount: string|null, message: string}>, summary: array{computed: int, previewed: int, skipped: int, failed: int}}
     */
    public function compute(int $company_id, int $year, ?string $period = null, bool $dry_run = false): array
    {
        $fiscal_year = FiscalYear::query()->withoutGlobalScopes()
            ->where('company_id', $company_id)
            ->where('year', $year)
            ->first();

        if (! $fiscal_year instanceof FiscalYear) {
            throw ValidationException::withMessages([
                'year' => [sprintf('Fiscal year %d does not exist for company %d.', $year, $company_id)],
            ]);
        }

        $periods = $this->periods($fiscal_year, $year, $period);
        $results = [];

        foreach ($periods as $fiscal_period) {
            $results[] = $this->processPeriod($company_id, $fiscal_period, $dry_run);
        }

        return [
            'company_id' => $company_id,
            'year' => $year,
            'periods' => $results,
            'summary' => [
                'computed' => $this->countStatus($results, 'computed'),
                'previewed' => $this->countStatus($results, 'preview'),
                'skipped' => $this->countStatus($results, 'skipped'),
                'failed' => $this->countStatus($results, 'failed'),
            ],
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, FiscalPeriod>
     */
    private function periods(FiscalYear $fiscal_year, int $year, ?string $period): \Illuminate\Database\Eloquent\Collection
    {
        $query = FiscalPeriod::query()
            ->where('fiscal_year_id', $fiscal_year->getKey())
            ->orderBy('start_date');

        if ($period === null || mb_trim($period) === '') {
            return $query->where('is_closed', false)->get();
        }

        if (preg_match('/^(?<year>\d{4})-(?<period>\d{1,3})$/', $period, $matches) !== 1 || (int) $matches['year'] !== $year) {
            throw ValidationException::withMessages([
                'period' => ['The period must use YYYY-N format and belong to the selected fiscal year.'],
            ]);
        }

        return $query->where('period_no', (int) $matches['period'])->get();
    }

    /**
     * @return array{fiscal_period_id: int, period: string, status: 'computed'|'preview'|'skipped'|'failed', vat_sales: string|null, vat_purchases: string|null, previous_credit: string|null, settlement_amount: string|null, message: string}
     */
    private function processPeriod(int $company_id, FiscalPeriod $period, bool $dry_run): array
    {
        $base = [
            'fiscal_period_id' => (int) $period->getKey(),
            'period' => sprintf('%04d-%02d', (int) $period->fiscal_year->year, $period->period_no),
        ];

        if ($period->is_closed) {
            return $this->result($base, 'skipped', 'Fiscal period is closed.');
        }

        $existing = VatSettlement::query()->withoutGlobalScopes()
            ->where('company_id', $company_id)
            ->where('fiscal_period_id', $period->getKey())
            ->first();

        if ($existing?->status === VatSettlementStatus::Confirmed) {
            return $this->result($base, 'skipped', 'VAT settlement is already confirmed.');
        }

        try {
            if ($dry_run) {
                $amounts = $this->settlement_service->preview($company_id, (int) $period->getKey());

                return [...$base, 'status' => 'preview', ...$amounts, 'message' => 'Computed without persistence.'];
            }

            $settlement = $this->settlement_service->compute($company_id, (int) $period->getKey());

            return [
                ...$base,
                'status' => 'computed',
                'vat_sales' => (string) $settlement->vat_sales,
                'vat_purchases' => (string) $settlement->vat_purchases,
                'previous_credit' => (string) $settlement->previous_credit,
                'settlement_amount' => (string) $settlement->settlement_amount,
                'message' => sprintf('Draft VAT settlement #%s computed.', $settlement->getKey()),
            ];
        } catch (Throwable $exception) {
            return $this->result($base, 'failed', $exception->getMessage());
        }
    }

    /**
     * @param  array{fiscal_period_id: int, period: string}  $base
     * @return array{fiscal_period_id: int, period: string, status: 'computed'|'preview'|'skipped'|'failed', vat_sales: string|null, vat_purchases: string|null, previous_credit: string|null, settlement_amount: string|null, message: string}
     */
    private function result(array $base, string $status, string $message): array
    {
        return [
            ...$base,
            'status' => $status,
            'vat_sales' => null,
            'vat_purchases' => null,
            'previous_credit' => null,
            'settlement_amount' => null,
            'message' => $message,
        ];
    }

    /**
     * @param  list<array{status: string}>  $results
     */
    private function countStatus(array $results, string $status): int
    {
        return count(array_filter($results, static fn (array $result): bool => $result['status'] === $status));
    }
}
