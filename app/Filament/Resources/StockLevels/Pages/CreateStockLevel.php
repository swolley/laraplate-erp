<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\StockLevels\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\StockLevels\StockLevelResource;

class CreateStockLevel extends CreateRecord
{
    #[\Override]
    protected static string $resource = StockLevelResource::class;
}
