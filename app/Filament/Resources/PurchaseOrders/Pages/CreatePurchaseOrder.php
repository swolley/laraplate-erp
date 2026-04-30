<?php

namespace Modules\ERP\Filament\Resources\PurchaseOrders\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\PurchaseOrders\PurchaseOrderResource;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;
}
