<?php

declare(strict_types=1);

namespace Modules\ERP\Tests\Stubs\Import;

use Modules\Core\Import\Contracts\BulkImporterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * An importer that implements only Core's base contract, not ERP's marker: the ERP import
 * command must refuse it.
 */
final class CoreOnlyImporter implements BulkImporterInterface
{
    public function import(?OutputInterface $output = null): int
    {
        return 0;
    }
}
