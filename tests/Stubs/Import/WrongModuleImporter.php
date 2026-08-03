<?php

declare(strict_types=1);

namespace Modules\ERP\Tests\Stubs\Import;

use Modules\CMS\Import\Contracts\BulkImporterInterface;

final class WrongModuleImporter implements BulkImporterInterface
{
    public function import(): int
    {
        return 0;
    }
}
