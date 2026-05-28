<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\ReturnOrders\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\ReturnOrders\ReturnOrderResource;
use Override;

final class CreateReturnOrder extends CreateRecord
{
    #[Override]
    protected static string $resource = ReturnOrderResource::class;
}
