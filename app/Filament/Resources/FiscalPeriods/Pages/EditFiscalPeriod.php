<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\FiscalPeriods\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Business\Filament\Resources\FiscalPeriods\FiscalPeriodResource;
use Override;

final class EditFiscalPeriod extends EditRecord
{
    #[Override]
    protected static string $resource = FiscalPeriodResource::class;
}
