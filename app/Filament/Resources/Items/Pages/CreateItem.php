<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Items\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\Items\ItemResource;
use Override;

class CreateItem extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = ItemResource::class;
}
