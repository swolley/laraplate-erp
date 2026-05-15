<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\StockLevels\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\StockLevels\StockLevelResource;

class ListStockLevels extends ListRecords
{
    #[\Override]
    protected static string $resource = StockLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
