<?php

declare(strict_types=1);

namespace Modules\ERP\Tests\Stubs;

/**
 * Minimal opportunity row used to exercise SalesPipelineService edge paths.
 */
final class SalesPipelineOpportunityRowDouble
{
    public function __construct(
        public OpportunityStatusDouble $status,
        public ?string $expected_value_doc,
        public ?string $expected_value_local,
        public mixed $won_at,
        public mixed $lost_at,
    ) {}
}
