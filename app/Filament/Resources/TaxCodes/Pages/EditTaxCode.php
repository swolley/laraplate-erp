<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\TaxCodes\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Business\Filament\Resources\TaxCodes\TaxCodeResource;
use Override;

final class EditTaxCode extends EditRecord
{
    #[Override]
    protected static string $resource = TaxCodeResource::class;
}
