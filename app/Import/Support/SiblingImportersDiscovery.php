<?php

declare(strict_types=1);

namespace Modules\ERP\Import\Support;

use Modules\Core\Import\Contracts\ImportPluginDiscoveryInterface;
use Modules\Core\Import\Support\FilesystemImportPluginDiscovery;
use Modules\ERP\Import\Contracts\BulkImporterInterface;

final readonly class SiblingImportersDiscovery implements ImportPluginDiscoveryInterface
{
    private FilesystemImportPluginDiscovery $discovery;

    public function __construct(?string $root_override = null)
    {
        $this->discovery = new FilesystemImportPluginDiscovery(
            label: 'laraplate-importers',
            defaultRoot: $root_override ?? base_path('../laraplate-importers'),
            contract: BulkImporterInterface::class,
        );
    }

    public function label(): string
    {
        return $this->discovery->label();
    }

    public function root(): ?string
    {
        return $this->discovery->root();
    }

    public function autoloadPath(?string $root = null): ?string
    {
        return $this->discovery->autoloadPath($root);
    }

    public function discoverImplementations(?string $root = null): array
    {
        return $this->discovery->discoverImplementations($root);
    }
}
