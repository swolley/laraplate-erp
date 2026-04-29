<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\TaxCodes\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\TaxCodes\TaxCodeResource;
use Override;

final class CreateTaxCode extends CreateRecord
{
    #[Override]
    protected static string $resource = TaxCodeResource::class;
}
