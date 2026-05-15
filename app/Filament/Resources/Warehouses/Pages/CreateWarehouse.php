<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Warehouses\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\Warehouses\WarehouseResource;

class CreateWarehouse extends CreateRecord
{
    #[\Override]
    protected static string $resource = WarehouseResource::class;
}
