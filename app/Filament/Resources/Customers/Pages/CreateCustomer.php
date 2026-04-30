<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Customers\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\Customers\CustomerResource;
use Override;

final class CreateCustomer extends CreateRecord
{
    #[Override]
    protected static string $resource = CustomerResource::class;
}
