<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\SupplierReturns\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\SupplierReturns\SupplierReturnResource;
use Override;

final class ListSupplierReturns extends ListRecords
{
    #[Override]
    protected static string $resource = SupplierReturnResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
