<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\FiscalPeriods\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Business\Filament\Resources\FiscalPeriods\FiscalPeriodResource;
use Override;

final class CreateFiscalPeriod extends CreateRecord
{
    #[Override]
    protected static string $resource = FiscalPeriodResource::class;
}
