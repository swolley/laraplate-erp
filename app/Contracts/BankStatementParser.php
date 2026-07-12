<?php

declare(strict_types=1);

namespace Modules\ERP\Contracts;

use Modules\ERP\Services\Banking\BankStatementLineData;

interface BankStatementParser
{
    public function supports(string $path, string $contents): bool;

    /**
     * @return list<BankStatementLineData>
     */
    public function parse(string $contents): array;
}
