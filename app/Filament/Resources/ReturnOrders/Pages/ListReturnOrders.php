<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\ReturnOrders\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\ReturnOrders\ReturnOrderResource;
use Override;

final class ListReturnOrders extends ListRecords
{
    #[Override]
    protected static string $resource = ReturnOrderResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
