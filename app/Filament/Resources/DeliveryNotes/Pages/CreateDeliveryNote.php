<?php

namespace Modules\ERP\Filament\Resources\DeliveryNotes\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\DeliveryNotes\DeliveryNoteResource;

class CreateDeliveryNote extends CreateRecord
{
    protected static string $resource = DeliveryNoteResource::class;
}
