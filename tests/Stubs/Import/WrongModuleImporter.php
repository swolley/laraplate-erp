<?php

declare(strict_types=1);

namespace Modules\ERP\Tests\Stubs\Import;

use Modules\CMS\Import\Contracts\BulkImporterInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class WrongModuleImporter implements BulkImporterInterface
{
    public function import(?OutputInterface $output = null): int
    {
        return 0;
    }
}
