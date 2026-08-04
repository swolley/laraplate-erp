<?php

declare(strict_types=1);

namespace Modules\ERP\Tests\Stubs\Import;

use Modules\ERP\Import\Contracts\BulkImporterInterface;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

final class FailingErpImporter implements BulkImporterInterface
{
    public function import(?OutputInterface $output = null): int
    {
        throw new RuntimeException('Expected ERP importer failure.');
    }
}
