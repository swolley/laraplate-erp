<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PriceLists\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\PriceLists\PriceListResource;
use Override;

final class CreatePriceList extends CreateRecord
{
    #[Override]
    protected static string $resource = PriceListResource::class;
}
