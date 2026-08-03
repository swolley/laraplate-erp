<?php

declare(strict_types=1);

namespace Modules\ERP\Tests\Stubs\Import;

use Modules\ERP\Import\Contracts\BulkImporterInterface;
use RuntimeException;

final class FailingErpImporter implements BulkImporterInterface
{
    public function import(): int
    {
        throw new RuntimeException('Expected ERP importer failure.');
    }
}
