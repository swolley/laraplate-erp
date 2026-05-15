<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Items\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\Items\ItemResource;

class CreateItem extends CreateRecord
{
    #[\Override]
    protected static string $resource = ItemResource::class;
}
