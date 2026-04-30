<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Quotations\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\Quotations\QuotationResource;
use Override;

final class ListQuotations extends ListRecords
{
    #[Override]
    protected static string $resource = QuotationResource::class;
}
