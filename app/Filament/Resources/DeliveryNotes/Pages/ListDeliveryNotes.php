<?php

namespace Modules\ERP\Filament\Resources\DeliveryNotes\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\DeliveryNotes\DeliveryNoteResource;

class ListDeliveryNotes extends ListRecords
{
    protected static string $resource = DeliveryNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
