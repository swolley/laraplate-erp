<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\FiscalPeriods\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\FiscalPeriods\FiscalPeriodResource;
use Override;

final class ListFiscalPeriods extends ListRecords
{
    #[Override]
    protected static string $resource = FiscalPeriodResource::class;
}
