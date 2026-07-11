<?php

declare(strict_types=1);

namespace Modules\ERP\Data\Returns;

final readonly class ReturnLineCreditOverride
{
    /**
     * @param  numeric-string  $quantity
     * @param  numeric-string  $unit_price
     */
    public function __construct(
        public int $source_line_id,
        public string $quantity,
        public string $unit_price,
    ) {}
}
