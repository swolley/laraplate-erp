<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Warehouses\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\Warehouses\WarehouseResource;
use Override;

class ListWarehouses extends ListRecords
{
    #[Override]
    protected static string $resource = WarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
