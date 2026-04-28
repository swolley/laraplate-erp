<?php

declare(strict_types=1);

namespace Modules\Business\Tests\Support;

use Override;
use Modules\Business\Models\FiscalPeriod;

/**
 * Test double that bypasses persistence for {@see FiscalPeriodCloser} unit tests.
 */
class FakeFiscalPeriod extends FiscalPeriod
{
    public bool $saved = false;

    #[Override]
    public function save(array $options = []): bool
    {
        $this->saved = true;

        return true;
    }
}
