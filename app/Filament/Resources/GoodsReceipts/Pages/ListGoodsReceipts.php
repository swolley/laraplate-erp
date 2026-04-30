<?php

namespace Modules\ERP\Filament\Resources\GoodsReceipts\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\GoodsReceipts\GoodsReceiptResource;

class ListGoodsReceipts extends ListRecords
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
