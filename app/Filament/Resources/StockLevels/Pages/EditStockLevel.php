<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\StockLevels\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\ERP\Filament\Resources\StockLevels\StockLevelResource;
use Override;

class EditStockLevel extends EditRecord
{
    #[Override]
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
