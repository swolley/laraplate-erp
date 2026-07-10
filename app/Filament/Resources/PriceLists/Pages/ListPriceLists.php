<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PriceLists\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\PriceLists\PriceListResource;
use Override;

final class ListPriceLists extends ListRecords
{
    #[Override]
    protected static string $resource = PriceListResource::class;

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
