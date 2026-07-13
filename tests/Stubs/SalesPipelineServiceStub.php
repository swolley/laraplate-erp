<?php

declare(strict_types=1);

namespace Modules\ERP\Tests\Stubs;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Enumerable;
use Modules\ERP\Services\Reporting\SalesPipelineService;

/**
 * Injects a fixed opportunity collection for SalesPipelineService edge-case tests.
 */
final class SalesPipelineServiceStub extends SalesPipelineService
{
    /**
     * @param  Collection<int, object>  $opportunities
     */
    public function __construct(
        private readonly Collection $opportunities,
    ) {}

    protected function loadOpportunities(int $company_id): Enumerable
    {
        return $this->opportunities;
    }
}
