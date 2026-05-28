<?php

declare(strict_types=1);

namespace Modules\ERP\Data\Pricing;

use Modules\ERP\Models\PartyPriceRule;
use Modules\ERP\Models\PriceListItem;

final readonly class PriceResolutionResult
{
    public function __construct(
        public PriceListItem $priceListItem,
        public string $baseUnitPrice,
        public string $resolvedUnitPrice,
        public ?PartyPriceRule $appliedRule = null,
    ) {}
}
