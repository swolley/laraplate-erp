<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\GoodsReceipts\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use Override;

class ListGoodsReceipts extends ListRecords
{
    #[Override]
    protected static string $resource = GoodsReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
