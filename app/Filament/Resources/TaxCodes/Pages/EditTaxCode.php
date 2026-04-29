<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\TaxCodes\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\ERP\Filament\Resources\TaxCodes\TaxCodeResource;
use Override;

final class EditTaxCode extends EditRecord
{
    #[Override]
    protected static string $resource = TaxCodeResource::class;
}
