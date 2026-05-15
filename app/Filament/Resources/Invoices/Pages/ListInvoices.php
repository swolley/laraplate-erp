<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Invoices\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\Invoices\InvoiceResource;

class ListInvoices extends ListRecords
{
    #[\Override]
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
