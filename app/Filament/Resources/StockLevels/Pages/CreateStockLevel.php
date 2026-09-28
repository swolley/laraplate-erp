<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\StockLevels\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\StockLevels\StockLevelResource;
use Override;

class CreateStockLevel extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = StockLevelResource::class;
}
