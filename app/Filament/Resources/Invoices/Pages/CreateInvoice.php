<?php

namespace Modules\ERP\Filament\Resources\Invoices\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\Invoices\InvoiceResource;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;
}
