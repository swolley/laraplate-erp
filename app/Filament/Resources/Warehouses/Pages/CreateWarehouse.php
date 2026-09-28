<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Warehouses\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\Warehouses\WarehouseResource;
use Override;

class CreateWarehouse extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = WarehouseResource::class;
}
