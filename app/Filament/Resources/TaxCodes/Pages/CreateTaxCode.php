<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\TaxCodes\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\TaxCodes\TaxCodeResource;
use Override;

final class CreateTaxCode extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = TaxCodeResource::class;
}
