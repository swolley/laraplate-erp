<?php

declare(strict_types=1);

namespace Modules\ERP\Tests\Stubs;

use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Contracts\ChartOfAccountsProvider;

/**
 * Configurable chart definition provider for installer tests.
 */
final class ArrayChartOfAccountsProviderStub implements ChartOfAccountsProvider
{
    /**
     * @param  list<array{code: string, name: string, kind: AccountKind, parent_code: string|null, meta?: array<string, mixed>}>  $definitions
     */
    public function __construct(
        private readonly array $definitions,
    ) {}

    public function definitions(): array
    {
        return $this->definitions;
    }
}
