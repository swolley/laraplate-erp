<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\FiscalPeriods\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Business\Filament\Resources\FiscalPeriods\FiscalPeriodResource;
use Override;

final class ListFiscalPeriods extends ListRecords
{
    #[Override]
    protected static string $resource = FiscalPeriodResource::class;
}
