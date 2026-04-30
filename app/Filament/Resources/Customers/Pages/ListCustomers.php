<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Customers\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\Customers\CustomerResource;
use Override;

final class ListCustomers extends ListRecords
{
    #[Override]
    protected static string $resource = CustomerResource::class;
}
