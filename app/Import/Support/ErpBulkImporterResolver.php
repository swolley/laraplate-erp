<?php

declare(strict_types=1);

namespace Modules\ERP\Import\Support;

use Illuminate\Contracts\Container\Container;
use Modules\Core\Import\Contracts\BulkImporterInterface as CoreBulkImporterInterface;
use Modules\Core\Import\Contracts\BulkImporterResolverInterface;
use Modules\Core\Import\Support\ContainerBulkImporterResolver;
use Modules\ERP\Import\Contracts\BulkImporterInterface;

final readonly class ErpBulkImporterResolver implements BulkImporterResolverInterface
{
    private ContainerBulkImporterResolver $resolver;

    public function __construct(Container $container)
    {
        $this->resolver = new ContainerBulkImporterResolver($container, BulkImporterInterface::class);
    }

    public function resolve(string $importerClass, array $parameters): CoreBulkImporterInterface
    {
        return $this->resolver->resolve($importerClass, $parameters);
    }

    public function contract(): string
    {
        return BulkImporterInterface::class;
    }
}
