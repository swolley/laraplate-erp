<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\SalesOrders\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\ERP\Filament\Resources\SalesOrders\SalesOrderResource;
use Override;

final class ListSalesOrders extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = SalesOrderResource::class;
}
