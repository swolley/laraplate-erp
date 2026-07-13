<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Reporting;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Enumerable;
use Modules\ERP\Casts\OpportunityStatus;
use Modules\ERP\Models\Opportunity;

/**
 * Generates operational sales pipeline summaries from CRM opportunities.
 */
final class SalesPipelineService
{
    /**
     * @param  array{won_from?: string|null, won_to?: string|null}  $filters
     * @return array{
     *     by_status: array<string, array{status: string, count: int, expected_value_doc: string, expected_value_local: string}>,
     *     total_count: int,
     *     won_count: int,
     *     lost_count: int,
     *     won_value_doc: string,
     *     won_value_local: string,
     *     total_expected_value_doc: string,
     *     total_expected_value_local: string,
     * }
     */
    public function generate(int $company_id, array $filters = []): array
    {
        /** @var array<string, array{status: string, count: int, expected_value_doc: numeric-string, expected_value_local: numeric-string}> $by_status */
        $by_status = [];

        foreach (OpportunityStatus::cases() as $status) {
            $by_status[$status->value] = $this->emptyStatusBucket($status->value);
        }

        $opportunities = $this->loadOpportunities($company_id);

        $total_count = 0;
        $won_count = 0;
        $lost_count = 0;
        $won_value_doc = 0.0;
        $won_value_local = 0.0;
        $total_expected_value_doc = 0.0;
        $total_expected_value_local = 0.0;

        foreach ($opportunities as $opportunity) {
            $status = $opportunity->status->value;
            $expected_value_doc = (float) ($opportunity->expected_value_doc ?? '0');
            $expected_value_local = (float) ($opportunity->expected_value_local ?? '0');

            if (! isset($by_status[$status])) {
                $by_status[$status] = $this->emptyStatusBucket($status);
            }

            $bucket = $by_status[$status];
            $bucket['count']++;
            $bucket['expected_value_doc'] = $this->formatAmount(
                (float) $bucket['expected_value_doc'] + $expected_value_doc,
            );
            $bucket['expected_value_local'] = $this->formatAmount(
                (float) $bucket['expected_value_local'] + $expected_value_local,
            );
            $by_status[$status] = $bucket;

            $total_count++;

            if ($opportunity->won_at !== null && $this->wonInRange($opportunity->won_at, $filters)) {
                $won_count++;
                $won_value_doc += $expected_value_doc;
                $won_value_local += $expected_value_local;
            }

            $lost_count += $opportunity->lost_at === null ? 0 : 1;
            $total_expected_value_doc += $expected_value_doc;
            $total_expected_value_local += $expected_value_local;
        }

        return [
            'by_status' => $by_status,
            'total_count' => $total_count,
            'won_count' => $won_count,
            'lost_count' => $lost_count,
            'won_value_doc' => $this->formatAmount($won_value_doc),
            'won_value_local' => $this->formatAmount($won_value_local),
            'total_expected_value_doc' => $this->formatAmount($total_expected_value_doc),
            'total_expected_value_local' => $this->formatAmount($total_expected_value_local),
        ];
    }

    /**
     * @return Enumerable<int, Opportunity>
     */
    protected function loadOpportunities(int $company_id): Enumerable
    {
        return Opportunity::query()
            ->where('company_id', $company_id)
            ->select([
                'status',
                'expected_value_doc',
                'expected_value_local',
                'won_at',
                'lost_at',
            ])
            ->orderBy('id')
            ->lazy(500);
    }

    /**
     * @return array{status: string, count: int, expected_value_doc: numeric-string, expected_value_local: numeric-string}
     */
    private function emptyStatusBucket(string $status): array
    {
        return [
            'status' => $status,
            'count' => 0,
            'expected_value_doc' => '0.0000',
            'expected_value_local' => '0.0000',
        ];
    }

    /**
     * @return numeric-string
     */
    private function formatAmount(float $amount): string
    {
        return number_format(round($amount, 4), 4, '.', '');
    }

    /**
     * @param  array{won_from?: string|null, won_to?: string|null}  $filters
     */
    private function wonInRange(CarbonInterface $won_at, array $filters): bool
    {
        $won_from = $filters['won_from'] ?? null;
        $won_to = $filters['won_to'] ?? null;

        if ($won_from !== null && $won_from !== '' && $won_at->lt(CarbonImmutable::parse($won_from)->startOfDay())) {
            return false;
        }

        return ! ($won_to !== null && $won_to !== '' && $won_at->gt(CarbonImmutable::parse($won_to)->endOfDay()));
    }
}
