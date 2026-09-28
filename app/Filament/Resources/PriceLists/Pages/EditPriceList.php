<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PriceLists\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\PriceLists\PriceListResource;
use Override;

final class EditPriceList extends EditRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = PriceListResource::class;
}
