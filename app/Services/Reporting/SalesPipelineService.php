<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Reporting;

use Modules\ERP\Casts\OpportunityStatus;
use Modules\ERP\Models\Opportunity;

/**
 * Generates operational sales pipeline summaries from CRM opportunities.
 */
final class SalesPipelineService
{
    /**
     * @return array{
     *     by_status: array<string, array{status: string, count: int, expected_value_doc: string, expected_value_local: string}>,
     *     total_count: int,
     *     won_count: int,
     *     lost_count: int,
     *     total_expected_value_doc: string,
     *     total_expected_value_local: string,
     * }
     */
    public function generate(int $company_id): array
    {
        /** @var array<string, array{status: string, count: int, expected_value_doc: numeric-string, expected_value_local: numeric-string}> $by_status */
        $by_status = [];

        foreach (OpportunityStatus::cases() as $status) {
            $by_status[$status->value] = $this->emptyStatusBucket($status->value);
        }

        $opportunities = Opportunity::query()
            ->where('company_id', $company_id)
            ->get([
                'status',
                'expected_value_doc',
                'expected_value_local',
                'won_at',
                'lost_at',
            ]);

        $total_count = 0;
        $won_count = 0;
        $lost_count = 0;
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
            $won_count += $opportunity->won_at === null ? 0 : 1;
            $lost_count += $opportunity->lost_at === null ? 0 : 1;
            $total_expected_value_doc += $expected_value_doc;
            $total_expected_value_local += $expected_value_local;
        }

        return [
            'by_status' => $by_status,
            'total_count' => $total_count,
            'won_count' => $won_count,
            'lost_count' => $lost_count,
            'total_expected_value_doc' => $this->formatAmount($total_expected_value_doc),
            'total_expected_value_local' => $this->formatAmount($total_expected_value_local),
        ];
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
}
