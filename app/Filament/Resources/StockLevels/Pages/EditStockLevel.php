<?php

namespace Modules\ERP\Filament\Resources\StockLevels\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\ERP\Filament\Resources\StockLevels\StockLevelResource;

class EditStockLevel extends EditRecord
{
    protected static string $resource = StockLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
