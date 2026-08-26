<?php

declare(strict_types=1);

namespace Modules\ERP\Console;

use Modules\Core\Console\AbstractImportCommand;
use Modules\Core\Import\Support\BulkImportRunner;
use Modules\ERP\Import\Support\ErpBulkImporterResolver;
use Modules\ERP\Import\Support\SiblingImportersDiscovery;
use Override;

final class ImportCommand extends AbstractImportCommand
{
    #[Override]
    protected $name = 'erp:import';

    #[Override]
    protected $description = 'Run a bulk ERP import through an external importer plugin <fg=yellow>(💼 Modules\ERP)</fg=yellow>';

    public function __construct(
        BulkImportRunner $runner,
        ErpBulkImporterResolver $resolver,
        SiblingImportersDiscovery $discovery,
    ) {
        parent::__construct($runner, $resolver, $discovery);
    }
}
