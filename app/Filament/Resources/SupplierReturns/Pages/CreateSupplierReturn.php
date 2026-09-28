<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\SupplierReturns\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\SupplierReturns\SupplierReturnResource;
use Override;

final class CreateSupplierReturn extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = SupplierReturnResource::class;
}
