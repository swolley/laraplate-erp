<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Items\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\Items\ItemResource;

class ListItems extends ListRecords
{
    #[\Override]
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
