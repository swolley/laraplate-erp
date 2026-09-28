<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Warehouses\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\Warehouses\WarehouseResource;
use Override;

class EditWarehouse extends EditRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = WarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
