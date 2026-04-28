<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\TaxCodes\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Business\Filament\Resources\TaxCodes\TaxCodeResource;
use Override;

final class ListTaxCodes extends ListRecords
{
    #[Override]
    protected static string $resource = TaxCodeResource::class;
}
