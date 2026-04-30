<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Customers\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\ERP\Filament\Resources\Customers\CustomerResource;
use Override;

final class EditCustomer extends EditRecord
{
    #[Override]
    protected static string $resource = CustomerResource::class;
}
