<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PriceLists\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\PriceLists\PriceListResource;
use Override;

final class CreatePriceList extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = PriceListResource::class;
}
