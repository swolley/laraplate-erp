<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\VatRegister\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\VatRegister\VatRegisterResource;
use Override;

final class ListVatRegisterEntries extends ListRecords
{
    #[Override]
    protected static string $resource = VatRegisterResource::class;
}
