<?php

declare(strict_types=1);

namespace Modules\Business\Contracts;

use Modules\Business\Casts\AccountKind;

/**
 * Pluggable chart of accounts definition for seeding / import.
 *
 * Each definition row describes one account. `parent_code` references another
 * row's `code` in the same definition set (null = root). The installer
 * resolves parents in topological order.
 *
 * @phpstan-type AccountDefinition array{code: string, name: string, kind: AccountKind, parent_code: string|null, meta?: array<string, mixed>}
 */
interface ChartOfAccountsProvider
{
    /**
     * @return list<AccountDefinition>
     */
    public function definitions(): array;
}
